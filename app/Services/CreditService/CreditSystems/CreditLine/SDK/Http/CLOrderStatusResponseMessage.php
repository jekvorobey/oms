<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Ответное сообщение на запрос проверки статуса заказа в системе CreditLine
 * Class CLOrderStatusResponseMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLOrderStatusResponseMessage
{
    /** Тело ответа */
    public CLOrderStatus $creditLineResponse;

    public function __construct()
    {
        $this->creditLineResponse = new CLOrderStatus();
    }
}
