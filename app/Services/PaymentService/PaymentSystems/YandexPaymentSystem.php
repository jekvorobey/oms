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
     * @throws \YandexCheckout\Common\Exceptions\ApiException
     * @throws \YandexCheckout\Common\Exceptions\BadApiRequestException
     * @throws \YandexCheckout\Common\Exceptions\ForbiddenException
     * @throws \YandexCheckout\Common\Exceptions\InternalServerError
     * @throws \YandexCheckout\Common\Exceptions\NotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ResponseProcessingException
     * @throws \YandexCheckout\Common\Exceptions\TooManyRequestsException
     * @throws \YandexCheckout\Common\Exceptions\UnauthorizedException
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;
        $idempotenceKey = uniqid('', true);
        
        $response = $this->yandexService->createPayment([
            'amount' => [
                'value' => number_format($order->price, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'payment_method_data' => [
                'type' => 'bank_card',
            ],
            'capture' => true,
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
                    'currency' => 'RUB',
                ],
                'vat_code' => 1,
                "payment_mode" => "full_prepayment",
                "payment_subject" => "commodity"
            ];
        }
    
        return $items;
    }
    
    /**
     * Получить от внешней системы ссылку страницы оплаты.
     *
     * @param Payment $payment
     * @return string
     */
    public function paymentLink(Payment $payment): string
    {
        return $payment->data['paymentUrl'];
    }

    /**
     * Обработать данные от платёжной ситсемы о совершении платежа.
     * @param  array  $data
     * @return void
     * @throws \YandexCheckout\Common\Exceptions\ApiException
     * @throws \YandexCheckout\Common\Exceptions\BadApiRequestException
     * @throws \YandexCheckout\Common\Exceptions\ExtensionNotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ForbiddenException
     * @throws \YandexCheckout\Common\Exceptions\InternalServerError
     * @throws \YandexCheckout\Common\Exceptions\NotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ResponseProcessingException
     * @throws \YandexCheckout\Common\Exceptions\TooManyRequestsException
     * @throws \YandexCheckout\Common\Exceptions\UnauthorizedException
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
                $this->capturePayment($payment, $localPayment);
                break;
            case PaymentStatus::SUCCEEDED:
                $this->succeededPayment($payment, $localPayment);
                break;
        }
    }

    /**
     * @param  \YandexCheckout\Model\PaymentInterface  $payment
     * @param  Payment  $localPayment
     * @throws \YandexCheckout\Common\Exceptions\ApiException
     * @throws \YandexCheckout\Common\Exceptions\BadApiRequestException
     * @throws \YandexCheckout\Common\Exceptions\ForbiddenException
     * @throws \YandexCheckout\Common\Exceptions\InternalServerError
     * @throws \YandexCheckout\Common\Exceptions\NotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ResponseProcessingException
     * @throws \YandexCheckout\Common\Exceptions\TooManyRequestsException
     * @throws \YandexCheckout\Common\Exceptions\UnauthorizedException
     */
    protected function capturePayment(\YandexCheckout\Model\PaymentInterface $payment, Payment $localPayment): void
    {
        $this->yandexService->capturePayment(
            [
                'amount' => [
                    'value' => $payment->getAmount()->getValue(),
                    'currency' => $payment->getAmount()->getCurrency(),
                ],
                'receipt' => [
                    "tax_system_code" => "2",
                    'email' => $localPayment->order->receiver_email,
                    'items' => $this->generateItems($localPayment->order->basket),
                ],
            ],
            $payment->getId(),
            uniqid('', true)
        );
    }

    /**
     * @param  \YandexCheckout\Model\PaymentInterface  $payment
     * @param  Payment  $localPayment
     */
    protected function succeededPayment(\YandexCheckout\Model\PaymentInterface $payment, Payment $localPayment)
    {
        $localPayment->status = \App\Models\Payment\PaymentStatus::PAID;
        $localPayment->payed_at = Carbon::now();
        $localPayment->save();
    }
    
    /**
     * Время в часах, в течение которого можно совершить платёж после его создания.
     * Если за эт овремя платёж не совершён - заказ отменяется.
     * Если не указано, то время бесконечно.
     *
     * @return int|null
     */
    public function duration(): ?int
    {
        return 1;
    }
}
