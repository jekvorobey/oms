<?php

namespace App\Services\CreditService;

use App\Models\Credit\Credit;
use App\Models\Order\Order;

/**
 * Class CreditService
 * @package App\Services\CreditService
 */
class CreditService
{
    public const CREDIT_ORDER_STATUS_REFUSED = 1;
    public const CREDIT_ORDER_STATUS_ANNULED = 2;
    public const CREDIT_ORDER_STATUS_IN_WORK = 3;
    public const CREDIT_ORDER_STATUS_SIGNED = 4;
    public const CREDIT_ORDER_STATUS_ACCEPTED = 5;
    public const CREDIT_ORDER_STATUS_IN_COMPLETED = 6;
    public const CREDIT_ORDER_STATUS_IN_STACK = 7;
    public const CREDIT_ORDER_STATUS_IN_CASH = 8;
    public const CREDIT_ORDER_STATUS_CASHED = 9;

    public const ORDER_RETURN_REASON_ID = 15;

    public function checkStatus(Order $order): ?array
    {
        $creditModel = new Credit();

        return $creditModel->creditSystem()->checkCreditOrder($order->number);
    }
}
