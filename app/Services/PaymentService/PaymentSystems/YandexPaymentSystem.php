<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Basket\Basket;
use App\Models\Payment\Payment;
use Carbon\Carbon;
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

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(Client::class);
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

        $response = $this->yandexService->createPayment([
            'amount' => [
                'value' => number_format($order->price, 2, '.', ''),
                'currency' => self::CURRENCY_RUB,
            ],
            'payment_method_data' => [
                'type' => 'bank_card', // важно! при смене способа оплаты может поменяться максимальный срок холдирования
            ],
            'capture' => false,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnLink,
            ],
            'description' => "Заказ №{$order->id}",
            'receipt' => [
                "tax_system_code" => "2",
                'email' => $order->customerEmail(),
                'items' => $this->generateItems($order->basket),
            ],
        ], $idempotenceKey);

        $data = $payment->data;
        $data['externalPaymentId'] = $response['id'];
        $data['paymentUrl'] = $response['confirmation']['confirmation_url'];
        $payment->data = $data;

        $payment->save();
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
        $notification = ($data['event'] === NotificationEventType::PAYMENT_SUCCEEDED)
            ? new NotificationSucceeded($data)
            : new NotificationWaitingForCapture($data);

        $payment = $this->yandexService->getPaymentInfo($notification->getObject()->getId());
        /** @var Payment $localPayment */
        $localPayment = Payment::query()
            ->where('data->externalPaymentId', $payment->id)
            ->firstOrFail();

        switch ($payment->getStatus()) {
            case PaymentStatus::WAITING_FOR_CAPTURE:
                $localPayment->status = \App\Models\Payment\PaymentStatus::HOLD;
                $localPayment->save();
                break;
            case PaymentStatus::SUCCEEDED:
                $localPayment->status = \App\Models\Payment\PaymentStatus::PAID;
                $localPayment->payed_at = Carbon::now();
                $localPayment->save();
                break;
            case PaymentStatus::CANCELED:
                $localPayment->status = \App\Models\Payment\PaymentStatus::TIMEOUT;
                $localPayment->save();
                break;
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
        $this->yandexService->capturePayment(
            [
                'amount' => [
                    'value' => $amount,
                    'currency' => self::CURRENCY_RUB,
                ],
                'receipt' => [
                    "tax_system_code" => "2",
                    'email' => $localPayment->order->customerEmail(),
                    'items' => $this->generateItems($localPayment->order->basket),
                ],
            ],
            $this->externalPaymentId($localPayment),
            uniqid('', true)
        );
    }

    /**
     * @param  Basket  $basket
     * @return array
     */
    protected function generateItems(Basket $basket)
    {
        $items = [];
        foreach ($basket->items as $item) {
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

        return $items;
    }
}
