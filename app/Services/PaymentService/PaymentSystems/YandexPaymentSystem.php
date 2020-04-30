<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use YandexCheckout\Client;
use YandexCheckout\Model\Notification\NotificationSucceeded;
use YandexCheckout\Model\Notification\NotificationWaitingForCapture;
use YandexCheckout\Model\NotificationEventType;
use YandexCheckout\Model\PaymentStatus;

/**
 * Class YandexPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class YandexPaymentSystem implements PaymentSystemInterface
{
    public const CURRENCY_RUB = 'RUB';
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
     * @param  Payment  $payment
     * @param  string  $returnLink
     * @throws \Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;
        $idempotenceKey = uniqid('', true);

        $paymentData = [
            'amount' => [
                'value' => number_format($order->price, 2, '.', ''),
                'currency' => self::CURRENCY_RUB,
            ],
            'payment_method_data' => [
                'type' => 'bank_card',
                // важно! при смене способа оплаты может поменяться максимальный срок холдирования
            ],
            'capture' => false,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnLink,
            ],
            'description' => "Заказ №{$order->id}",
            'receipt' => [
                "tax_system_code" => "2",
                'phone' => $order->customerPhone(),
                'items' => $this->generateItems($order),
            ],
            'metadata' => [
                'source' => config('app.url')
            ]
        ];
        /*$email = $order->customerEmail();
        if ($email) {
            $paymentData['receipt']['email'] = $email;
        }*/
        $this->logger->info('Create payment data', $paymentData);
        $response = $this->yandexService->createPayment($paymentData, $idempotenceKey);
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
    }

    /**
     * Получить от внешней системы ссылку страницы оплаты.
     *
     * @param Payment $payment
     * @return string|null
     */
    public function paymentLink(Payment $payment): ?string
    {
        return $payment->data['paymentUrl'] ?? null;
    }

    /**
     * Получить от id оплаты во внешней системе.
     *
     * @param Payment $payment
     * @return string|null
     */
    public function externalPaymentId(Payment $payment): ?string
    {
        return $payment->data['externalPaymentId'] ?? null;
    }

    /**
     * Обработать данные от платёжной ситсемы о совершении платежа.
     * @param  array  $data
     * @return void
     * @throws \Exception
     */
    public function handlePushPayment(array $data): void
    {
        $this->logger->info('Handle external payment');
        $notification = ($data['event'] === NotificationEventType::PAYMENT_SUCCEEDED)
            ? new NotificationSucceeded($data)
            : new NotificationWaitingForCapture($data);

        $this->logger->info('External event data', $notification->jsonSerialize());

        $payment = $this->yandexService->getPaymentInfo($notification->getObject()->getId());
        $metadata = $payment->metadata ? $payment->metadata->toArray() : null;

        $this->logger->info('Metadata', $metadata);

        if ($metadata && $metadata['source'] && $metadata['source'] !== config('app.url')) {
            $this->logger->info('Redirect payment', [
                'proxy' => $metadata['source']
            ]);
            $client = new \GuzzleHttp\Client();
            $client->request('POST', $metadata['source'] . route('handler.yandexPayment', [], false), [
                'form_params' => $data
            ]);
        } else {
            $this->logger->info('Process payment', [
                'external_payment_id' => $payment->id,
                'status' => $payment->getStatus()
            ]);
            /** @var Payment $localPayment */
            $localPayment = Payment::query()
                ->where('data->externalPaymentId', $payment->id)
                ->firstOrFail();

            switch ($payment->getStatus()) {
                case PaymentStatus::WAITING_FOR_CAPTURE:
                    $this->logger->info('Set holded', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = \App\Models\Payment\PaymentStatus::HOLD;
                    $localPayment->save();
                    break;
                case PaymentStatus::SUCCEEDED:
                    $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = \App\Models\Payment\PaymentStatus::PAID;
                    $localPayment->payed_at = Carbon::now();
                    $localPayment->save();
                    break;
                case PaymentStatus::CANCELED:
                    $this->logger->info('Set canceled', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = \App\Models\Payment\PaymentStatus::TIMEOUT;
                    $localPayment->save();
                    break;
            }
        }
    }

    /**
     * Время в часах, в течение которого можно совершить платёж после его создания.
     * Если за это время платёж не совершён - заказ отменяется.
     * Если не указано, то время бесконечно.
     *
     * @return int|null
     */
    public function duration(): ?int
    {
        return 1;
    }

    /**
     * @param Payment $localPayment
     * @param $amount
     * @throws \Exception
     */
    public function commitHoldedPayment(Payment $localPayment, $amount)
    {
        $captureData = [
            'amount' => [
                'value' => $amount,
                'currency' => self::CURRENCY_RUB,
            ],
            'receipt' => [
                "tax_system_code" => "2",
                'phone' => $localPayment->order->customerPhone(),
                'items' => $this->generateItems($localPayment->order),
            ],
        ];
        /*$email = $localPayment->order->customerEmail();
        if ($email) {
            $captureData['receipt']['email'] = $email;
        }*/
        $this->logger->info('Start commit holded payment', $captureData);
        $response = $this->yandexService->capturePayment(
            $captureData,
            $this->externalPaymentId($localPayment),
            uniqid('', true)
        );

        $this->logger->info('Commit result', $response->jsonSerialize());
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function generateItems(Order $order)
    {
        $items = [];
        foreach ($order->basket->items as $item) {
            $items[] = [
                'description' => $item->name,
                'quantity' => $item->qty,
                'amount' => [
                    'value' => number_format($item->price, 2, '.', ''),
                    'currency' => self::CURRENCY_RUB,
                ],
                'vat_code' => 1,
                "payment_mode" => "full_prepayment",
                "payment_subject" => "commodity"
            ];
        }
        if ($order->delivery_price) {
            $items[] = [
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => number_format($order->delivery_price, 2, '.', ''),
                    'currency' => self::CURRENCY_RUB,
                ],
                'vat_code' => 1,
                "payment_mode" => "full_prepayment",
                "payment_subject" => "service"
            ];
        }

        return $items;
    }
}
