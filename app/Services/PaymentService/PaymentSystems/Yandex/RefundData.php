<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Order\OrderReturn;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Request\Refunds\CreateRefundRequest;
use YooKassa\Request\Refunds\CreateRefundRequestBuilder;

class RefundData
{
    public const TAX_SYSTEM_CODE = 3;

    public const VAT_CODE_DEFAULT = 1;
    public const VAT_CODE_0_PERCENT = 2;
    public const VAT_CODE_10_PERCENT = 3;
    public const VAT_CODE_20_PERCENT = 4;

    /**
     * Формирование данных для возврата платежа
     */
    public function getData(string $paymentId, OrderReturn $orderReturn): CreateRefundRequestBuilder
    {
        $builder = CreateRefundRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount(number_format($orderReturn->price, 2, '.', ''), CurrencyCode::RUB))
            ->setPaymentId($paymentId)
            ->setReceiptPhone($orderReturn->order->customerPhone())
            ->setTaxSystemCode(self::TAX_SYSTEM_CODE);

        if ($orderReturn->is_delivery) {
            $builder->addReceiptShipping(
                'Доставка',
                number_format($orderReturn->price, 2, '.', ''),
                self::VAT_CODE_DEFAULT,
                PaymentMode::FULL_PAYMENT,
                PaymentSubject::SERVICE,
            );
        } else {
            foreach ($orderReturn->items as $item) {
                $itemValue = $item->price / $item->qty;
                $builder->addReceiptItem( //@TODO:: Сделать по аналогии с созданием платежа
                    $item->name,
                    number_format($itemValue, 2, '.', ''),
                    $item->qty,
                    self::VAT_CODE_DEFAULT
                );
            }
        }

        return $builder;
    }
}
