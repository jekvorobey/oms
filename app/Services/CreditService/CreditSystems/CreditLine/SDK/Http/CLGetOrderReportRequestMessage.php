<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Dates;

/**
 * Сообщение получения отчета по заказам за определенный период от сервиса CreditLine
 * Class CLGetOrderReportRequestMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLGetOrderReportRequestMessage
{
    public Dates $getOrderDates;

    public function __construct()
    {
        $this->getOrderDates = new Dates();
    }
}
