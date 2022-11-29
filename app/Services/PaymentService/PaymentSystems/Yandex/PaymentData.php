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
use YooKassa\Model\PaymentData\B2b\Sberbank\VatDataType;
use YooKassa\Model\PaymentData\PaymentDataB2bSberbank;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\CreatePaymentRequestBuilder;
use YooKassa\Request\Payments\Payment\CreateCaptureRequest;
use YooKassa\Request\Payments\Payment\CreateCaptureRequestBuilder;

class PaymentData extends OrderData
{
    //Холдирование: false вкл., true - выкл.
    public const DEFAULT_PAYMENT_CAPTURE = true;
    public const DEFAULT_PAYMENT_B2B_CAPTURE = true;

    /**
     * Формирование данных для создания платежа
     */
    public function getCreateData(Order $order, string $returnLink): CreatePaymentRequestBuilder
    {
        $builder = CreatePaymentRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($order->cashless_price))
            ->setCurrency(CurrencyCode::RUB)
            ->setCapture(self::DEFAULT_PAYMENT_CAPTURE)
            ->setConfirmation([
                'type' => ConfirmationType::REDIRECT,
                'returnUrl' => $returnLink,
            ])
            ->setDescription("Заказ №{$order->id}")
            ->setMetadata(['source' => config('app.url')])
            ->setReceiptPhone($order->customerPhone())
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);

        if ($order->payment_method_id === PaymentMethod::B2B_SBERBANK) {
            $this->buildForB2BSberbank($builder, $order);
        }

        return $builder;
    }

    private function buildForB2BSberbank(CreatePaymentRequestBuilder $builder, Order $order): void
    {
        $b2bSberbankData = new PaymentDataB2bSberbank();
        $b2bSberbankData->setPaymentPurpose("Оплата заказа №{$order->id}");

        $b2bSberbankVatData = new VatData();
        $vatAmount = $this->getVatAmount($order);
        if ($vatAmount) {
            $b2bSberbankVatData->setType(VatDataType::MIXED);
            $b2bSberbankVatData->setAmount(new MonetaryAmount($vatAmount));
        } else {
            $b2bSberbankVatData->setType(VatDataType::UNTAXED);
        }

        $b2bSberbankData->setVatData($b2bSberbankVatData);

        $builder->setPaymentMethodData($b2bSberbankData);
        $builder->setCapture(self::DEFAULT_PAYMENT_B2B_CAPTURE);
    }

    /**
     * Формирование данных для подтверждения холдированного платежа
     */
    public function getCommitData(Payment $localPayment, $amount): CreateCaptureRequestBuilder
    {
        $builder = CreateCaptureRequest::builder();

        return $builder
            ->setAmount(new MonetaryAmount($amount))
            ->setCurrency(CurrencyCode::RUB)
            ->setReceiptPhone($localPayment->order->customerPhone())
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);
    }

    /**
     * @throws PimException
     */
    protected function getVatAmount(Order $order): float|int
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

            if ($vatValue > 0) {
                $amount += $item->price * $vatValue / 100;
            }
        }

        return $amount;
    }
}
