<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\IncomeReceiptData;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\RefundReceiptData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\Notification\NotificationFactory;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentStatus;
use App\Models\Payment\PaymentType;
use YooKassa\Model\Payment as YooKassaPayment;

/**
 * Class YandexPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class YandexPaymentSystem implements PaymentSystemInterface
{
    /** @var SDK\Client */
    private $yandexService;
    /** @var Logger */
    private $logger;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(SDK\Client::class);
        $this->logger = Log::channel('payments');
    }

    /**
     * @throws \Exception
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
     * Обработать данные от платёжной ситсемы о совершении платежа.
     * @param array $data
     * @throws \Exception
     */
    public function handlePushPayment(array $data): void
    {
        $this->logger->info('Handle external payment');
        $notification = (new NotificationFactory())->factory($data);

        $this->logger->info('External event data', $notification->jsonSerialize());

        $paymentId = $notification->getEvent() === NotificationEventType::REFUND_SUCCEEDED
            ? $notification->getObject()->getPaymentId()
            : $notification->getObject()->getId();

        $payment = $this->yandexService->getPaymentInfo($paymentId);

        if ($payment) {
            $metadata = $payment->metadata ? $payment->metadata->toArray() : null;
            $this->logger->info('Metadata', $metadata);
            $this->logger->info('Process payment', [
                'external_payment_id' => $paymentId,
                'status' => $payment->getStatus(),
            ]);

            $this->processExternalPayment($paymentId, $payment, $notification);
        }
    }

    private function processExternalPayment(
        string $paymentId,
        YooKassaPayment $payment,
        AbstractNotification $notification
    ): void {
        /** @var Payment $localPayment */
        $localPayment = Payment::query()
            ->where('data->externalPaymentId', $paymentId)
            ->firstOrFail();
        $order = $localPayment->order;

        $localPayment->payment_type = $payment->payment_method ? $payment->payment_method->getType() : null;

        switch ($payment->getStatus()) {
            case PaymentStatus::WAITING_FOR_CAPTURE:
                $this->logger->info('Set holded', ['local_payment_id' => $localPayment->id]);

                $localPayment->status = Models\Payment\PaymentStatus::HOLD;
                $localPayment->yandex_expires_at = $notification->getObject()->getExpiresAt();
                $localPayment->save();

                break;
            case PaymentStatus::SUCCEEDED:
                $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);

                $localPayment->status = Models\Payment\PaymentStatus::PAID;
                $localPayment->save();

                if ($notification->getEvent() === NotificationEventType::REFUND_SUCCEEDED) {
                    $refundId = $notification->getObject()->getId();
                    if ($refundId) {
                        $this->createRefundReceipt($paymentId, $refundId);
                    }
                }
                break;
            case PaymentStatus::CANCELED:
                $this->logger->info('Set canceled', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::TIMEOUT;
                $localPayment->save();

                break;
        }

        $order->can_partially_cancelled = !in_array($localPayment->payment_type, PaymentType::typesWithoutPartiallyCancel(), true);
        $order->save();
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
        $paymentData = new PaymentData();
        $builder = $paymentData->getCommitData($localPayment, $amount);

        $request = $builder->build();
        $this->logger->info('Start commit holded payment', $request->toArray());

        try {
            $order = $localPayment->order;

            if ($amount > $order->spent_certificate) {
                $response = $this->yandexService->capturePayment($request, $localPayment->external_payment_id);
                $this->logger->info('Commit result', $response->jsonSerialize());
            } else {
                $localPayment->status = Models\Payment\PaymentStatus::PAID;
                $localPayment->save();

                if ($localPayment->external_payment_id) {
                    $this->cancel($localPayment->external_payment_id);
                }
            }

            if ($localPayment->refund_sum > 0) {
                $order = $localPayment->order;
                $this->createRefundAllReceipt($order, $localPayment);
                $this->createIncomeReceipt($order, $localPayment);

                $order->done_return_sum = $localPayment->refund_sum;
                $order->save();
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Error from payment system', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            report($exception);
        }
    }

    /**
     * @inheritDoc
     * @throws \YooKassa\Common\Exceptions\ApiException
     * @throws \YooKassa\Common\Exceptions\BadApiRequestException
     * @throws \YooKassa\Common\Exceptions\ExtensionNotFoundException
     * @throws \YooKassa\Common\Exceptions\ForbiddenException
     * @throws \YooKassa\Common\Exceptions\InternalServerError
     * @throws \YooKassa\Common\Exceptions\NotFoundException
     * @throws \YooKassa\Common\Exceptions\ResponseProcessingException
     * @throws \YooKassa\Common\Exceptions\TooManyRequestsException
     * @throws \YooKassa\Common\Exceptions\UnauthorizedException
     */
    public function refund(string $paymentId, OrderReturn $orderReturn): array
    {
        try {
            $order = $orderReturn->order;

            if ($order->isFullyPaidByCertificate()) {
                $refundReceiptData = new RefundReceiptData();
                $returnReceiptBuilder = $refundReceiptData->getRefundReceiptPartiallyData($paymentId, $orderReturn);
                $request = $returnReceiptBuilder->build();
                $this->logger->info('Start create refund receipt', $request->toArray());

                $response = $this->yandexService->createReceipt($request);
                $this->logger->info('Return receipt', $response->jsonSerialize());
            } else {
                $refundData = new RefundData();
                $builder = $refundData->getCreateData($paymentId, $orderReturn);
                $request = $builder->build();

                $this->logger->info('Start refund payment', $request->toArray());
                $response = $this->yandexService->createRefund($request);

                if ($response) {
                    $this->logger->info('refund payment result', $response->jsonSerialize());
                }

                $orderReturn->refund_id = $response->getId();
                $orderReturn->save();
            }

            return $response->jsonSerialize();
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund', ['local_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return [];
    }

    /**
     * @inheritDoc
     * @throws \YooKassa\Common\Exceptions\ApiException
     * @throws \YooKassa\Common\Exceptions\BadApiRequestException
     * @throws \YooKassa\Common\Exceptions\ExtensionNotFoundException
     * @throws \YooKassa\Common\Exceptions\ForbiddenException
     * @throws \YooKassa\Common\Exceptions\InternalServerError
     * @throws \YooKassa\Common\Exceptions\NotFoundException
     * @throws \YooKassa\Common\Exceptions\ResponseProcessingException
     * @throws \YooKassa\Common\Exceptions\TooManyRequestsException
     * @throws \YooKassa\Common\Exceptions\UnauthorizedException
     */
    public function cancel(string $paymentId): array
    {
        return $this->yandexService->cancelPayment($paymentId)->jsonSerialize();
    }

    /**
     * Создание чека прихода
     */
    public function createIncomeReceipt(Order $order, Payment $payment): void
    {
        try {
            $receiptData = new IncomeReceiptData();
            $builder = $receiptData->getReceiptData($order, $payment->external_payment_id);
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
    public function createRefundAllReceipt(Order $order, Payment $payment): void
    {
        try {
            $refundAllItemsReceiptData = new RefundReceiptData();
            $builder = $refundAllItemsReceiptData->getRefundReceiptAllItemsData($order, $payment->external_payment_id);
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
    private function createRefundReceipt(string $paymentId, string $refundId): void
    {
        try {
            /** @var OrderReturn $orderReturn */
            $orderReturn = OrderReturn::query()
                ->where('refund_id', $refundId)
                ->firstOrFail();

            $refundData = new RefundReceiptData();
            $returnReceiptBuilder = $refundData->getRefundReceiptPartiallyData($paymentId, $orderReturn);
            $request = $returnReceiptBuilder->build();
            $this->logger->info('Start create refund receipt', $request->toArray());

            $response = $this->yandexService->createReceipt($request);
            $this->logger->info('Return receipt', $response->jsonSerialize());

            $order = $orderReturn->order;
            $order->done_return_sum += $orderReturn->price;
            $order->save();
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }
}
