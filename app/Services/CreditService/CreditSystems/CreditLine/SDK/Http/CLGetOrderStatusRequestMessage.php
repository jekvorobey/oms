<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Сообщение получения статуса заказа в системе CreditLine
 * Class CLGetOrderStatusRequestMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLGetOrderStatusRequestMessage
{
    /** Номер заказа в системе Партнера */
    public string $NumOrder;

    public function __construct(string $numOrder)
    {
        $this->NumOrder = $numOrder;
    }
}
