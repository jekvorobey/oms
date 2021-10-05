<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
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
    /**
     * Формирование данных для создания платежа
     */
    public function getCreateData(Order $order, string $returnLink): CreatePaymentRequestBuilder
    {
        $builder = CreatePaymentRequest::builder();
        return $builder
            ->setAmount(new MonetaryAmount($order->price))
            ->setCurrency(CurrencyCode::RUB)
            ->setCapture(false)
            ->setConfirmation([
                'type' => ConfirmationType::REDIRECT,
                'returnUrl' => $returnLink,
            ])
            ->setDescription("Заказ №{$order->id}")
            ->setMetadata(['source' => config('app.url')])
            ->setReceiptPhone($order->customerPhone())
            ->setTaxSystemCode(Tax::TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME);
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
            ->setAmount(new MonetaryAmount($amount))
            ->setCurrency(CurrencyCode::RUB)
            ->setReceiptPhone($localPayment->order->customerPhone())
            ->setTaxSystemCode(Tax::TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME);
    }
}
