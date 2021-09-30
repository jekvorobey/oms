<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Payment\PaymentType;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YooKassa\Client;
use YooKassa\Model\ConfirmationType;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Notification\NotificationCanceled;
use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\Notification\NotificationWaitingForCapture;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentStatus;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\Payment\CreateCaptureRequest;

class Payment
{
    public const TAX_SYSTEM_CODE = 3;

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
     * @throws \Exception
     */
    public function createExternalPayment(\App\Models\Payment\Payment $payment, string $returnLink): void
    {
        $order = $payment->order;
        $idempotenceKey = uniqid('', true);
        $builder = CreatePaymentRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount(number_format($order->price, 2, '.', ''), CurrencyCode::RUB))
            ->setCapture(false)
            ->setConfirmation([
                'type' => ConfirmationType::REDIRECT,
                'returnUrl' => $returnLink,
            ])
            ->setDescription("Заказ №{$order->id}")
            ->setMetadata(['source' => config('app.url')])
            ->setReceiptPhone($order->customerPhone())
            ->setTaxSystemCode(self::TAX_SYSTEM_CODE);
        $this->addReceiptItems($order, $builder);

        $request = $builder->build();
        $this->logger->info('Create payment data', $request->toArray());

        try {
            $response = $this->yandexService->createPayment($request, $idempotenceKey);
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
        switch ($data['event']) {
            case NotificationEventType::PAYMENT_SUCCEEDED:
                $notification = new NotificationSucceeded($data);
                break;
            case NotificationEventType::PAYMENT_CANCELED:
                $notification = new NotificationCanceled($data);
                break;
            case NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE:
                $notification = new NotificationWaitingForCapture($data);
                break;
            default:
                return;
        }

        $this->logger->info('External event data', $notification->jsonSerialize());

        $payment = $this->yandexService->getPaymentInfo($notification->getObject()->getId());
        $metadata = $payment->metadata ? $payment->metadata->toArray() : null;

        $this->logger->info('Metadata', $metadata);

        if ($metadata && $metadata['source'] && $metadata['source'] !== config('app.url')) {
            $this->logger->info('Redirect payment', [
                'proxy' => $metadata['source'],
            ]);
            $client = new GuzzleClient();
            $client->request('POST', $metadata['source'] . route('handler.yandexPayment', [], false), [
                'form_params' => $data,
            ]);
        } else {
            $this->logger->info('Process payment', [
                'external_payment_id' => $payment->id,
                'status' => $payment->getStatus(),
            ]);
            /** @var Payment $localPayment */
            $localPayment = Payment::query()
                ->where('data->externalPaymentId', $payment->id)
                ->firstOrFail();

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
                    break;
                case PaymentStatus::CANCELED:
                    $this->logger->info('Set canceled', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = Models\Payment\PaymentStatus::TIMEOUT;
                    $localPayment->save();
                    break;
            }

            $order = $localPayment->order;
            $order->can_partially_cancelled = !in_array($localPayment->payment_type, PaymentType::typesWithoutPartiallyCancel(), true);
            $order->save();
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
        $builder = CreateCaptureRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount(number_format($amount, 2, '.', ''), CurrencyCode::RUB))
            ->setReceiptPhone($localPayment->order->customerPhone())
            ->setTaxSystemCode(self::TAX_SYSTEM_CODE);
//        $email = $localPayment->order->customerEmail();
//        if ($email) {
//            $builder->setReceiptEmail($email);
//        }
        $this->addReceiptItems($localPayment->order, $builder);

        $request = $builder->build();
        $this->logger->info('Start commit holded payment', $request->toArray());

        try {
            $response = $this->yandexService->capturePayment(
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
    public function cancel(string $paymentId): array
    {
        return $this->yandexService->cancelPayment($paymentId)->jsonSerialize();
    }
}
