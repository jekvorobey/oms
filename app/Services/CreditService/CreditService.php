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
    public const ORDER_RETURN_REASON_ID = 15;

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
}
