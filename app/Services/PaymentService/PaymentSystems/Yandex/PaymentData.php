<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use YooKassa\Model\ConfirmationType;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use App\Models\Payment\Payment;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\CreatePaymentRequestBuilder;
use YooKassa\Request\Payments\Payment\CreateCaptureRequest;
use YooKassa\Request\Payments\Payment\CreateCaptureRequestBuilder;

class PaymentData
{
    public const TAX_SYSTEM_CODE = 3;

    /**
     * Формирование данных для создания платежа
     */
    public function getCreateData(Order $order, string $returnLink): CreatePaymentRequestBuilder
    {
        $builder = CreatePaymentRequest::builder();
        return $builder
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
    }

    /**
     * Формирование данных для подтверждения холдированного платежа
     */
    public function getCommitData(Payment $localPayment, $amount): CreateCaptureRequestBuilder
    {
        $builder = CreateCaptureRequest::builder();
//        $email = $localPayment->order->customerEmail();
//        if ($email) {
//            $builder->setReceiptEmail($email);
//        }
        return $builder
            ->setAmount(new MonetaryAmount(number_format($amount, 2, '.', ''), CurrencyCode::RUB))
            ->setReceiptPhone($localPayment->order->customerPhone())
            ->setTaxSystemCode(self::TAX_SYSTEM_CODE);
    }
}
