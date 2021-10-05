<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\OrderReturn;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Request\Refunds\CreateRefundRequest;
use YooKassa\Request\Refunds\CreateRefundRequestBuilder;

class RefundData
{
    /**
     * Формирование данных для возврата платежа
     */
    public function getData(string $paymentId, OrderReturn $orderReturn): CreateRefundRequestBuilder
    {
        $builder = CreateRefundRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($orderReturn->price))
            ->setCurrency(CurrencyCode::RUB)
            ->setPaymentId($paymentId)
            ->setReceiptPhone($orderReturn->order->customerPhone())
            ->setTaxSystemCode(Tax::TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME);

        if ($orderReturn->is_delivery) {
            $builder->addReceiptShipping(
                'Доставка',
                $orderReturn->price,
                VatCode::CODE_DEFAULT,
                PaymentMode::FULL_PAYMENT,
                PaymentSubject::SERVICE,
            );
        } else {
            foreach ($orderReturn->items as $item) {
                $itemValue = $item->price / $item->qty;
                $builder->addReceiptItem( //@TODO:: Сделать по аналогии с созданием платежа
                    $item->name,
                    $itemValue,
                    $item->qty,
                    VatCode::CODE_DEFAULT
                );
            }
        }

        return $builder;
    }
}
