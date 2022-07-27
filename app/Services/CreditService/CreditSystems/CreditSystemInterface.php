<?php

namespace App\Services\CreditService\CreditSystems;

/**
 * Interface CreditSystemInterface
 * @package App\Services\CreditService\CreditSystems
 */
interface CreditSystemInterface
{
    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function checkCreditOrder(string $id): array;
}
