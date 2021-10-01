<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Payment\PaymentType;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YooKassa\Client;
use YooKassa\Common\AbstractPaymentRequestBuilder;
use YooKassa\Common\AbstractRequestBuilder;
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
    /** @var AbstractRequestBuilder */
    private $builder;
    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(Client::class);
        $this->logger = Log::channel('payments');
        $this->builder = CreatePaymentRequest::builder();
    }

    public function getPaymentData(): AbstractRequestBuilder
    {

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
}
