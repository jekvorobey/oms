<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\OrderReturn;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Request\Refunds\CreateRefundRequest;
use YooKassa\Request\Refunds\CreateRefundRequestBuilder;

class RefundData
{
    /**
     * Формирование данных для возврата платежа
     */
    public function getCreateData(string $paymentId, OrderReturn $orderReturn): CreateRefundRequestBuilder
    {
        $order = $orderReturn->order;
        $restCashlessReturnPrice = max(0, $order->remaining_price - $order->spent_certificate);
        $returnCashless = min($restCashlessReturnPrice, $orderReturn->price);

        $builder = CreateRefundRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($returnCashless))
            ->setCurrency(CurrencyCode::RUB)
            ->setPaymentId($paymentId)
            ->setReceiptPhone($orderReturn->order->customerPhone())
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);

        return $builder;
    }
}
