<?php

namespace App\Services\PaymentService\PaymentSystems\Raiffeisen;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentReceipt;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\IncomeReceiptData;
use Illuminate\Support\Facades\Log;
use Raiffeisen\Ecom\ClientException;
use Raiffeisen\Ecom\Client as RaiffeisenClient;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Throwable;
use Exception;

class RaiffeisenPaymentSystem implements PaymentSystemInterface
{
    private RaiffeisenClient $raiffeisenService;
    private LoggerInterface|Logger $logger;

    /**
     * RaiffeisenPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->raiffeisenService = resolve(RaiffeisenClient::class);
        $this->logger = Log::channel('payments');
    }

    /**
     * @throws Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;

        $amount = round($order->price, 2);
        $orderId = $order->id . "_" . $payment->id;
        $query = [
            'successUrl' => $returnLink,
            'locale' => 'ru',
            'paymentMethod' => 'ONLY_SBP',
        ];
        $this->logger->info('Create payment data', ['amount' => $amount, 'orderId' => $orderId, 'query' => $query]);

        try {
            $paymentLink = $this->raiffeisenService->getPayUrl($amount, $orderId, $query);
            $this->logger->info('Create payment result', ['paymentLink' => $paymentLink]);

            $payment->external_payment_id = $orderId;
            $payment->payment_link = $paymentLink;
            $ok = $payment->save();
            if (!$ok) {
                $this->logger->error('Payment not saved after create', [
                    'payment_id' => $payment->id,
                ]);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Error from payment system', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            report($exception);
        }
    }

    /**
     * Обработать данные от платёжной системы о совершении платежа.
     * @param array $data
     * @throws Exception
     */
    public function handlePushPayment(array $data): void
    {
        $this->logger->info('Handle external payment');
        $notification = $data;

        $this->logger->info('External event data', $notification);

        if (($notification['id'] ?? null) === null) {
            $this->processRefundSucceeded($notification);
            return;
        }

        $paymentId = $notification['id'] ?? null;
        $payment = $this->raiffeisenService->getPaymentInfo($paymentId);

        if ($payment) {
            $metadata = $payment->metadata?->toArray();
            $this->logger->info('Metadata', $metadata);
            $this->logger->info('Process payment', [
                'external_payment_id' => $paymentId,
                'status' => $payment->getStatus(),
            ]);
            /** @var Payment $localPayment */
            $localPayment = Payment::byExternalPaymentId($paymentId)->firstOrFail();

            $this->processExternalPayment($localPayment, $payment);
        }
    }

    public function processRefundSucceeded(array $notification): void
    {
        $refundId = $notification['refundId'] ?? null;
        $paymentId = $notification['paymentId'] ?? null;

        if ($paymentId && $refundId) {
            /** @var Payment $payment */
            $payment = Payment::byExternalPaymentId($paymentId)->firstOrFail();

            /** @var OrderReturn $orderReturn */
            $orderReturn = OrderReturn::query()
                ->where('refund_id', $refundId)
                ->firstOrFail();

            $this->createRefundReceipt($payment, $orderReturn);
        }
    }

    public function processExternalPayment(Payment $localPayment, array $paymentInfo): void
    {
        switch ($paymentInfo['code'] ?? null) {
            case "SUCCESS":
                $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = PaymentStatus::PAID;
                $localPayment->payment_type = $paymentInfo['code']['transaction']['paymentMethod'] ?? null;
                $localPayment->save();

                break;
            case "CANCEL":
                $order = $localPayment->order;
                // Если была оплата ПС+доплата и частично отменили на всю сумму доплаты, то платеж отменился, а у нас отменять не надо
                if ($order->remaining_price && $order->remaining_price <= $order->spent_certificate) {
                    break;
                }
                $this->logger->info('Set canceled', [
                    'local_payment_id' => $localPayment->id,
                    'cancel_reason' => '',
                ]);
                $localPayment->status = PaymentStatus::TIMEOUT;
                $localPayment->cancel_reason = '';
                $localPayment->save();

                break;
        }
    }

    /**
     * Время в часах, в течение которого можно совершить платёж после его создания.
     * Если за это время платёж не совершён - заказ отменяется.
     * Если не указано, то время бесконечно.
     */
    public function duration(): ?int
    {
        return 1;
    }

    /**
     * Подтверждение холдированной оплаты
     */
    public function commitHoldedPayment(Payment $localPayment, $amount): void
    {
    }

    public function refund(Payment $payment, OrderReturn $orderReturn): array
    {
        return [];
    }

    public function cancel(string $paymentId): array
    {
        return [];
    }

    /**
     * Создание чека прихода
     */
    public function createIncomeReceipt(Payment $payment, bool $isFullPayment): void
    {
        try {
            $receiptData = new IncomeReceiptData();
            $receiptData->setIsFullPayment($isFullPayment);
            $builder = $receiptData->getReceiptData($payment->order, $payment->external_payment_id);
            $request = $builder->build();

            $paymentReceipt = $this->createPaymentReceipt($payment, $request->toArray(), PaymentReceipt::TYPE_INCOME);

            $this->logger->info('Start create receipt', $request->toArray());
            $receiptResponse = $this->raiffeisenService->createReceipt($request)?->jsonSerialize();
            $this->logger->info('Creating income receipt result', $receiptResponse);

            $this->updateResponsePaymentReceipt($paymentReceipt, $receiptResponse);
        } catch (Throwable $exception) {
            $this->logger->error('Error creating receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Создание чека возврата всех позиций
     */
    public function createRefundAllReceipt(Payment $payment): void
    {
    }

    /**
     * Создание чека возврата отмененных позиций
     */
    private function createRefundReceipt(Payment $payment, OrderReturn $orderReturn): array
    {
        $result = OrderReturn::query()
            ->where('refund_id', $orderReturn->id)
            ->where('payment_id', $payment->id)
            ->firstOrFail();

        return [$result];
    }

    public function paymentInfo(Payment $payment): ?array
    {
        try {
            $orderTransaction = $this->raiffeisenService->getOrderTransaction($payment->external_payment_id);
        } catch (ClientException $e) {
            $this->logger->info('Error payment info', ['message' => $e]);
        }
        $this->logger->info('Get payment info', ['orderTransaction' => $orderTransaction ?? []]);

        return $orderTransaction ?? [];
    }

    /**
     * @param array $payment
     */
    public function updatePaymentStatus(Payment $localPayment, $payment): void
    {
        $this->processExternalPayment($localPayment, $payment);
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditPrepaymentReceipt(Payment $payment): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditReceipt(Payment $payment): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditPaymentReceipt(Payment $payment): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createReturnReceipt(Payment $payment, int $payAttribute, ?bool $isMerchant = true): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function sendReceipt(Payment $payment, array $receipt, int $type): ?array
    {
        return null;
    }

    private function createPaymentReceipt(Payment $payment, array $request, int $receiptType): PaymentReceipt
    {
        $paymentReceipt = new PaymentReceipt();
        $paymentReceipt->payment_id = $payment->id;
        $paymentReceipt->order_id = $payment->order_id;
        $paymentReceipt->sum = $payment->sum;
        $paymentReceipt->receipt_type = $receiptType;
        $paymentReceipt->request = $request;
        $paymentReceipt->save();

        return $paymentReceipt;
    }

    private function updateResponsePaymentReceipt(PaymentReceipt $paymentReceipt, ?array $receiptResponse = []): void
    {
        $paymentReceipt->response = $receiptResponse;
        $paymentReceipt->payed_at = now();
        $paymentReceipt->save();
    }
}
