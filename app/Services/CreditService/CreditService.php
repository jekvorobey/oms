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

    public function checkStatus(Order $order): ?array
    {
        $creditModel = new Credit();

        return $creditModel->creditSystem()->checkCreditOrder($order->number);
    }
}
