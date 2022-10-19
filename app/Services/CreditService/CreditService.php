<?php

namespace App\Services\CreditService;

use App\Models\Credit\Credit;
use App\Models\Order\Order;
use App\Models\Payment\Payment;

/**
 * Class CreditService
 * @package App\Services\CreditService
 */
class CreditService
{
    public const ORDER_RETURN_REASON_ID = 15;

    public const CREDIT_PAYMENT_RECEIPT_TYPE_PREPAYMENT = 1;
    public const CREDIT_PAYMENT_RECEIPT_TYPE_ON_CREDIT = 2;
    public const CREDIT_PAYMENT_RECEIPT_TYPE_PAYMENT = 3;

    public function getCreditOrder(Order $order): ?array
    {
        $creditModel = new Credit();

        return $creditModel->creditSystem()->getCreditOrder($order->number);
    }

    public function checkCreditOrder(Order $order): ?array
    {
        $creditModel = new Credit();

        return $creditModel->creditSystem()->checkCreditOrder($order);
    }

    public function createCreditPayment(Order $order, int $receiptType): ?Payment
    {
        $creditModel = new Credit();

        return $creditModel->creditSystem()->createCreditPayment($order, $receiptType);
    }
}
