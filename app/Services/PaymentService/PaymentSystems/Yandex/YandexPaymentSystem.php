<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\Notification\NotificationCanceled;
use YooKassa\Model\Notification\NotificationRefundSucceeded;
use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\Notification\NotificationWaitingForCapture;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentInterface;
use YooKassa\Model\PaymentStatus;
use GuzzleHttp\Client as GuzzleClient;
use App\Models\Payment\PaymentType;

/**
 * Class YandexPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class YandexPaymentSystem implements PaymentSystemInterface
{
    /** @var SDK\Client */
    private $localYandexService;
    /** @var Logger */
    private $logger;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->localYandexService = resolve(SDK\Client::class);
        $this->logger = Log::channel('payments');
    }

    /**
     * @throws \Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;
        $idempotenceKey = uniqid('', true);
        $paymentData = new PaymentData();
        $builder = $paymentData->getCreateData($order, $returnLink);
        $request = $builder->build();
        $this->logger->info('Create payment data', $request->toArray());

        try {
            $response = $this->localYandexService->createPayment($request, $idempotenceKey);
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
        $payment = $this->localYandexService->getPaymentInfo($notification->getObject()->getId());

        if ($payment) {
            $metadata = $payment->metadata ? $payment->metadata->toArray() : null;

            $this->logger->info('Metadata', $metadata);

            $this->logger->info('Process payment', [
                'external_payment_id' => $payment->id,
                'status' => $payment->getStatus(),
            ]);
            /** @var Payment $localPayment */
            $localPayment = Payment::query()
                ->where('data->externalPaymentId', $payment->id)
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

                    if ($order->is_partially_cancelled) {
                        $this->createRefundAllItemsReceipt($order, $this->externalPaymentId($localPayment));
                        $this->createIncomeReceipt($order, $localPayment);
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
        $idempotenceKey = uniqid('', true);
        $paymentData = new PaymentData();
        $builder = $paymentData->getCommitData($localPayment, $amount);

        $request = $builder->build();
        $this->logger->info('Start commit holded payment', $request->toArray());

        try {
            $response = $this->localYandexService->capturePayment(
                $request,
                $this->externalPaymentId($localPayment),
                $idempotenceKey
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
        $refundData = new RefundData();
        $builder = $refundData->getRefundData($paymentId, $orderReturn);
        $request = $builder->build();

        $this->logger->info('Start return payment', $request->toArray());
        $response = $this->localYandexService->createRefund($request, uniqid('', true));
        $this->logger->info('Return payment result', $response->jsonSerialize());

        // @TODO::Вынести в handlePushPayment
        $this->createRefundReceipt($paymentId, $orderReturn);

        return $response->jsonSerialize();
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
        $this->localYandexService->cancelPayment($paymentId)->jsonSerialize();

        return [];
    }

    /**
     * Создание чека прихода
     */
    public function createIncomeReceipt(Models\Order\Order $order, Payment $payment): array
    {
        try {
            $receiptData = new ReceiptData();
            $builder = $receiptData->getReceiptData($order, $payment->data['externalPaymentId']);
            $request = $builder->build();
            $this->logger->info('Start create receipt', $request->toArray());
            $idempotenceKey = uniqid('', true);

            return $this->localYandexService->createReceipt($request, $idempotenceKey)->jsonSerialize();
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
            $refundAllItemsReceiptData = new RefundData();
            $builder = $refundAllItemsReceiptData->getReturnReceiptAllItemdData($order, $paymentId);
            $request = $builder->build();

            $this->logger->info('Start creating refund receipt', $request);
            $data = $this->localYandexService->createReceipt($request)->jsonSerialize();
            $this->logger->info('Return creating refund receipt result', $data);
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    /**
     * Создание чека возврата отмененных позиций
     */
    public function createRefundReceipt(string $paymentId, OrderReturn $orderReturn): void
    {
        try {
            $refundData = new RefundData();
            $returnReceiptBuilder = $refundData->getReturnReceiptData($paymentId, $orderReturn);
            $request = $returnReceiptBuilder->build();
            $this->logger->info('Start create refund receipt', $request->toArray());

            $response = $this->localYandexService->createReceipt($request, uniqid('', true));
            $this->logger->info('Return receipt', $response->jsonSerialize());
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating refund receipt', ['yandex_payment_id' => $paymentId, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }
}
