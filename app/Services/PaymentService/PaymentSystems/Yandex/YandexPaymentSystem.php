<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\Yandex\Exceptions\Receipt;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\IncomeReceiptData;
use App\Services\PaymentService\PaymentSystems\Yandex\Receipt\RefundReceiptData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Pim\Services\CertificateService\CertificateService;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\Notification\NotificationCanceled;
use YooKassa\Model\Notification\NotificationRefundSucceeded;
use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\Notification\NotificationWaitingForCapture;
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
    /** @var CertificateService */
    private $certificateService;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(SDK\Client::class);
        $this->logger = Log::channel('payments');
        $this->certificateService = resolve(CertificateService::class);
    }

    /**
     * @throws \Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;

        $paymentData = new PaymentData();
        $builder = $paymentData->getCreateData($order, $returnLink);
        $request = $builder->build();
        $this->logger->info('Create payment data', $request->toArray());

        try {
            $response = $this->yandexService->createPayment($request);
            $this->logger->info('Create payment result', $response->jsonSerialize());

            $data = $payment->data;
            $data['externalPaymentId'] = $response['id'];
            $data['paymentUrl'] = $response['confirmation']['confirmation_url'];
            $payment->data = $data;

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
     * Получить от внешней системы ссылку страницы оплаты.
     */
    public function paymentLink(Payment $payment): ?string
    {
        return $payment->data['paymentUrl'] ?? null;
    }

    /**
     * Получить от id оплаты во внешней системе.
     */
    public function externalPaymentId(Payment $payment): ?string
    {
        return $payment->data['externalPaymentId'] ?? null;
    }

    /**
     * Обработать данные от платёжной ситсемы о совершении платежа.
     * @param array $data
     * @throws \Exception
     */
    public function handlePushPayment(array $data): void
    {
        $this->logger->info('Handle external payment');
        $notification = $this->getNotification($data);

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

    private function getNotification(array $data): AbstractNotification
    {
        switch ($data['event']) {
            case NotificationEventType::PAYMENT_SUCCEEDED:
                return new NotificationSucceeded($data);
            case NotificationEventType::PAYMENT_CANCELED:
                return new NotificationCanceled($data);
            case NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE:
                return new NotificationWaitingForCapture($data);
            case NotificationEventType::REFUND_SUCCEEDED:
                return new NotificationRefundSucceeded($data);
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
                $localPayment->payed_at = Carbon::now();
                $localPayment->save();

                switch ($notification->getEvent()) {
                    case NotificationEventType::PAYMENT_SUCCEEDED:
                        if ($localPayment->refund_sum > 0) {
                            $this->createRefundAllItemsReceipt($order, $this->externalPaymentId($localPayment));
                            $this->createIncomeReceipt($order, $localPayment);
                        }
                        break;
                    case NotificationEventType::REFUND_SUCCEEDED:
                        $refundId = $notification->getObject()->getId();
                        if ($refundId) {
                            $this->createRefundReceipt($paymentId, $refundId);
                        }
                        break;
                }
                break;
            case PaymentStatus::CANCELED:
                $this->logger->info('Set canceled', ['local_payment_id' => $localPayment->id]);
                $localPayment->status = Models\Payment\PaymentStatus::TIMEOUT;
                $localPayment->save();

                $this->createRefundAllItemsReceipt($order, $payment->id);
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
            $response = $this->yandexService->capturePayment(
                $request,
                $this->externalPaymentId($localPayment)
            );
            $this->logger->info('Commit result', $response->jsonSerialize());
        } catch (\Throwable $exception) {
            $this->logger->error('Error from payment system', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
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
            $refundData = new RefundReceiptData();
            $builder = $refundData->getRefundData($paymentId, $orderReturn);
            $request = $builder->build();

            $this->logger->info('Start return payment', $request->toArray());
            $response = $this->yandexService->createRefund($request);

            if ($response) {
                $this->logger->info('Return payment result', $response->jsonSerialize());
            }

            $orderReturn->refund_id = $response->id;
            $orderReturn->save();

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
    public function createIncomeReceipt(Models\Order\Order $order, Payment $payment): void
    {
        try {
            $paymentId = $order->isFullyPaidByCertificate() ? $this->getCertificatePaymentId($order) : $payment->data['externalPaymentId'];
            $receiptData = new IncomeReceiptData();
            $builder = $receiptData->getReceiptData($order, $paymentId);
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
    public function createRefundAllItemsReceipt(Order $order, string $paymentId): void
    {
        try {
            $refundAllItemsReceiptData = new RefundReceiptData();
            $builder = $refundAllItemsReceiptData->getRefundReceiptAllItemsData($order, $paymentId);
            $request = $builder->build();

            $this->logger->info('Start creating refund receipt', $request->toArray());
            $data = $this->yandexService->createReceipt($request)->jsonSerialize();
            $this->logger->info('Return creating refund receipt result', $data);
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Создание чека возврата отмененных позиций
     */
    public function createRefundReceipt(string $paymentId, string $refundId): void
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
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Получение id платежа юкассы покупки подарочного сертификата
     */
    private function getCertificatePaymentId(Order $order): ?string
    {
        $certificate = current($order->certificates);

        if ($certificate['id']) {
            $this->logger->info('Fully payed by certificate', ['certificate_id' => $certificate['id'], 'order_id' => $order->id]);

            $certificateQuery = $this->certificateService->certificateQuery();
            $certificateQuery->id($certificate['id']);
            $certificateInfo = $this->certificateService->certificates($certificateQuery);

            if ($certificateInfo) {
                $this->logger->info('Certificate info', ['certificate_info' => $certificateInfo]);
                $certificateRequests = $certificateInfo->pluck('request_id')->toArray();

                /** @var Models\Basket\BasketItem $certificateBasketItem */
                $certificateBasketItem = Models\Basket\BasketItem::query()
                    ->whereIn('product->request_id', $certificateRequests)
                    ->with('basket.order.payments')
                    ->firstOrFail();

                $certificatePayment = $certificateBasketItem->basket->order->payments->first();
                $result = $certificatePayment->data['externalPaymentId'];

                $this->logger->info('Certificate payment id', ['payment_id' => $result]);
            } else {
                throw new Receipt('Certificates not found');
            }
        } else {
            throw new Receipt('Certificate id in order not found');
        }

        return $result;
    }
}
