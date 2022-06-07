<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
use Pim\Core\PimException;
use YooKassa\Model\ConfirmationType;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use App\Models\Payment\Payment;
use YooKassa\Model\PaymentData\B2b\Sberbank\VatData;
use YooKassa\Model\PaymentData\PaymentDataB2bSberbank;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\CreatePaymentRequestBuilder;
use YooKassa\Request\Payments\Payment\CreateCaptureRequest;
use YooKassa\Request\Payments\Payment\CreateCaptureRequestBuilder;

class PaymentData extends OrderData
{
    /**
     * Формирование данных для создания платежа
     * @throws PimException
     */
    public function getCreateData(Order $order, string $returnLink): CreatePaymentRequestBuilder
    {
        $builder = CreatePaymentRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($order->cashless_price))
            ->setCurrency(CurrencyCode::RUB)
            ->setCapture(false)
            ->setConfirmation([
                'type' => ConfirmationType::REDIRECT,
                'returnUrl' => $returnLink,
            ])
            ->setDescription("Заказ №{$order->id}")
            ->setMetadata(['source' => config('app.url')])
            ->setReceiptPhone($order->customerPhone())
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);

        if ($order->paymentMethod == PaymentMethod::B2B_SBERBANK) {
            $b2bSberbankData = new PaymentDataB2bSberbank();
            $b2bSberbankData->setPaymentPurpose("Оплата заказа №{$order->id}");

            $b2bSberbankVatData = new VatData();
            $b2bSberbankVatData->setType('mixed');
            $b2bSberbankVatData->setAmount(new MonetaryAmount($this->getVatAmount($order)));

            $b2bSberbankData->setVatData($b2bSberbankVatData);

            $builder->setPaymentMethodData($b2bSberbankData);
            $builder->setCapture(true);
        }

        return $builder;
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
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);
    }

    /**
     * @throws PimException
     */
    protected function getVatAmount(Order $order)
    {
        $amount = 0;
        $offerIds = $order->basket->items->pluck('offer_id')->all();
        [$offers, $merchants] = $this->loadOffersAndMerchants($offerIds, $order);
        foreach ($order->basket->items as $item) {
            if ($item->isCanceled()) {
                continue;
            }

            $offer = $offers[$item->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;
            if (!isset($offer, $merchant)) {
                continue;
            }

            $vatValue = $this->getMerchantVatValue($offer, $merchant);
            $amount += $vatValue ? $item->price * $vatValue : 0;
        }

        return $amount;
    }
}
