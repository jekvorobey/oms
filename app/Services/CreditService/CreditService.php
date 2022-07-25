<?php

namespace App\Services\CreditService;

use App\Models\Order\Order;

/**
 * Class CreditService
 * @package App\Services\CreditService
 */
class CreditService
{
    public function checkStatus(Order $order)
    {
        $number = $order->number;
    }
}
