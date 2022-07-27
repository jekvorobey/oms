<?php

namespace App\Services\CreditService\CreditSystems;

/**
 * Interface CreditSystemInterface
 * @package App\Services\CreditService\CreditSystems
 */
interface CreditSystemInterface
{
    public const ORDER_RETURN_REASON_ID = 15;

    public const CREDIT_ORDER_STATUS_REFUSED = 0;
    public const CREDIT_ORDER_STATUS_ANNULED = 1;
    public const CREDIT_ORDER_STATUS_IN_WORK = 2;
    public const CREDIT_ORDER_STATUS_SIGNED = 3;
    public const CREDIT_ORDER_STATUS_ACCEPTED = 4;
    public const CREDIT_ORDER_STATUS_IN_COMPLETED = 5;
    public const CREDIT_ORDER_STATUS_IN_STACK = 6;
    public const CREDIT_ORDER_STATUS_IN_CASH = 7;
    public const CREDIT_ORDER_STATUS_CASHED = 8;

    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function checkCreditOrder(string $id): ?array;
}
