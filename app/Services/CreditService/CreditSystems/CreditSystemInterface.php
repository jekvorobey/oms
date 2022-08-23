<?php

namespace App\Services\CreditService\CreditSystems;

use App\Models\Order\Order;

/**
 * Interface CreditSystemInterface
 * @package App\Services\CreditService\CreditSystems
 */
interface CreditSystemInterface
{
    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function getCreditOrder(string $id): ?array;

    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function checkCreditOrder(Order $order): ?array;
}
