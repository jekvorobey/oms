<?php

namespace App\Services\CreditService;

use App\Models\Credit\Credit;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Services\CreditService\CreditSystems\CreditLine\CreditLineSystem;

/**
 * Class CreditService
 * @package App\Services\CreditService
 */
class CreditService
{
    public function checkStatus(Order $order): ?array
    {
        $creditModel = new Credit();

        $creditOrder = $creditModel->creditSystem()->checkCreditOrder($order->number);


        return $creditOrder;
    }
}
