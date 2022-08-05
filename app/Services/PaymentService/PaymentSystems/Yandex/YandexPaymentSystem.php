<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\IncomeReceiptData;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\RefundReceiptData;
use Exception;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YooKassa\Client;
use YooKassa\Common\Exceptions\ApiException;
use YooKassa\Common\Exceptions\BadApiRequestException;
use YooKassa\Common\Exceptions\ExtensionNotFoundException;
use YooKassa\Common\Exceptions\ForbiddenException;
use YooKassa\Common\Exceptions\InternalServerError;
use YooKassa\Common\Exceptions\NotFoundException;
use YooKassa\Common\Exceptions\ResponseProcessingException;
use YooKassa\Common\Exceptions\TooManyRequestsException;
use YooKassa\Common\Exceptions\UnauthorizedException;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\Notification\NotificationFactory;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentStatus;
use YooKassa\Model\Payment as YooKassaPayment;

/**
 * Class YandexPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class YandexPaymentSystem implements PaymentSystemInterface
{
    /** @var Client */
    private $yandexService;
    /** @var Logger */
    private $logger;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(Client::class);
        $this->logger = Log::channel('payments');
    }

    /**
     * @throws Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;

        $request = (new PaymentData())
            ->getCreateData($order, $returnLink)
            ->build();
        $this->logger->info('Create payment data', $request->toArray());

        try {
            $response = $this->yandexService->createPayment($request);
            $this->logger->info('Create payment result', $response->jsonSerialize());

            $payment->external_payment_id = $response->getId();
            $payment->payment_link = $response->getConfirmation()->getConfirmationUrl();
            $ok = $payment->save();
            if (!$ok) {
                $this->logger->error('Payment not saved after create', [
                    'payment_id' => $payment->id,
                ]);
            }
        } catch (\Throwable $exception) {
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
        $notification = (new NotificationFactory())->factory($data);

        $this->logger->info('External event data', $notification->jsonSerialize());

        if ($notification->getEvent() === NotificationEventType::REFUND_SUCCEEDED) {
            $this->processRefundSucceeded($notification);
            return;
        }

        $paymentId = $notification->getObject()->getId();
        $payment = $this->yandexService->getPaymentInfo($paymentId);

        if ($payment) {
            $metadata = $payment->metadata ? $payment->metadata->toArray() : null;
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

    private function processRefundSucceeded(AbstractNotification $notification): void
    {
        $refundId = $notification->getObject()->getId();
        $paymentId = $notification->getObject()->getPaymentId();

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

    private function processExternalPayment(Payment $localPayment, YooKassaPayment $payment): void
    {
        switch ($payment->status) {
            case PaymentStatus::PENDING:
                $this->logger->info('Set waiting', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::WAITING;
                $localPayment->payment_type = $payment->payment_method ? $payment->payment_method->getType() : null;
                $localPayment->save();

                break;
            case PaymentStatus::WAITING_FOR_CAPTURE:
                $this->logger->info('Set holded', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::HOLD;
                $localPayment->yandex_expires_at = $payment->getExpiresAt();
                $localPayment->payment_type = $payment->payment_method ? $payment->payment_method->getType() : null;
                $localPayment->save();

                break;
            case PaymentStatus::SUCCEEDED:
                $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::PAID;
                $localPayment->payment_type = $payment->payment_method ? $payment->payment_method->getType() : null;
                $localPayment->save();

                break;
            case PaymentStatus::CANCELED:
                $order = $localPayment->order;
                // Если была оплата ПС+доплата и частично отменили на всю сумму доплаты, то платеж в Юкассе отменился, а у нас отменять не надо
                if ($order->remaining_price && $order->remaining_price <= $order->spent_certificate) {
                    break;
                }
                $this->logger->info('Set canceled', [
                    'local_payment_id' => $localPayment->id,
                    'cancel_reason' => $payment->getCancellationDetails()->reason,
                ]);
                $localPayment->status = Models\Payment\PaymentStatus::TIMEOUT;
                $localPayment->cancel_reason = $payment->getCancellationDetails()->reason;
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
        try {
            if ($amount <= 0) {
                // Если была частичная оплата сертификатом и к моменту подтверждения платежа отменили всю сумму доплаты
                // То в Юкассе платеж отменяем, а в системе подтверждаем, чтобы заказ остался
                $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::PAID;
                $localPayment->save();

                if ($localPayment->external_payment_id) {
                    $this->cancel($localPayment->external_payment_id);
                }
                return;
            }

            $paymentData = new PaymentData();
            $builder = $paymentData->getCommitData($localPayment, $amount);
            $request = $builder->build();

            $this->logger->info('Start commit holded payment', $request->toArray());
            $response = $this->yandexService->capturePayment($request, $localPayment->external_payment_id);
            $this->logger->info('Commit result', $response->jsonSerialize());
        } catch (\Throwable $exception) {
            $this->logger->error('Error from payment system', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            report($exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function refund(Payment $payment, OrderReturn $orderReturn): array
    {
        try {
            $order = $orderReturn->order;

            if ($order->remaining_price <= $order->spent_certificate) {
                return $this->createRefundReceipt($payment, $orderReturn);
            }

            $refundData = new RefundData();
            $builder = $refundData->getCreateData($payment->external_payment_id, $orderReturn);
            $request = $builder->build();

            $this->logger->info('Start refund payment', $request->toArray());
            $response = $this->yandexService->createRefund($request);

            if ($response) {
                $this->logger->info('refund payment result', $response->jsonSerialize());
            }

            $orderReturn->refund_id = $response->getId();
            $orderReturn->save();

            return $response->jsonSerialize();
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund', ['local_payment_id' => $payment->external_payment_id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return [];
    }

    /**
     * @inheritDoc
     * @throws ApiException
     * @throws BadApiRequestException
     * @throws ExtensionNotFoundException
     * @throws ForbiddenException
     * @throws InternalServerError
     * @throws NotFoundException
     * @throws ResponseProcessingException
     * @throws TooManyRequestsException
     * @throws UnauthorizedException
     */
    public function cancel(string $paymentId): array
    {
        $this->logger->info('Start cancel payment', ['local_payment_id' => $paymentId]);

        $response = $this->yandexService->cancelPayment($paymentId);

        $this->logger->info('Cancel payment result', $response->jsonSerialize());

        return $response->jsonSerialize();
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
            $this->logger->info('Start create receipt', $request->toArray());

            $this->yandexService->createReceipt($request)->jsonSerialize();
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Создание чека возврата всех позиций
     */
    public function createRefundAllReceipt(Payment $payment): void
    {
        try {
            $refundAllItemsReceiptData = new RefundReceiptData();
            $refundAllItemsReceiptData->setIsFullPayment($payment->is_fullpayment_receipt_sent);
            $builder = $refundAllItemsReceiptData->getRefundReceiptAllItemsData($payment->order, $payment->external_payment_id);
            $request = $builder->build();

            $this->logger->info('Start creating refund receipt', $request->toArray());
            $data = $this->yandexService->createReceipt($request)->jsonSerialize();
            $this->logger->info('Return creating refund receipt result', $data);
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $payment->external_payment_id, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Создание чека возврата отмененных позиций
     */
    private function createRefundReceipt(Payment $payment, OrderReturn $orderReturn): array
    {
        try {
            $refundData = new RefundReceiptData();
            $refundData->setIsFullPayment($payment->is_fullpayment_receipt_sent);
            $returnReceiptBuilder = $refundData->getRefundReceiptPartiallyData($payment->external_payment_id, $orderReturn);
            $request = $returnReceiptBuilder->build();
            $this->logger->info('Start create refund receipt', $request->toArray());

            $response = $this->yandexService->createReceipt($request);
            $this->logger->info('Return receipt', $response->jsonSerialize());
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $payment->external_payment_id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return $response->jsonSerialize();
    }

    /**
     * @throws NotFoundException
     * @throws ResponseProcessingException
     * @throws ApiException
     * @throws ExtensionNotFoundException
     * @throws BadApiRequestException
     * @throws InternalServerError
     * @throws ForbiddenException
     * @throws TooManyRequestsException
     * @throws UnauthorizedException
     */
    public function paymentInfo(Payment $payment): ?YooKassaPayment
    {
        return $this->yandexService->getPaymentInfo($payment->external_payment_id);
    }

    /**
     * @param YooKassaPayment $payment
     */
    public function updatePaymentStatus(Payment $localPayment, $payment): void
    {
        $this->processExternalPayment($localPayment, $payment);
    }
}
