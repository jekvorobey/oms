<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Сообщение заявки на кредит
 * Class CLRequestMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLRequestMessage
{
    /** Тело заявки (параметры заявки на кредит) */
    public CLRequest $creditLineRequest;

    public function __construct()
    {
        $this->creditLineRequest = new CLRequest();
    }
}
