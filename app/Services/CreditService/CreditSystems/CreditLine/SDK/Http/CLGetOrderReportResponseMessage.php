<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\OrderReport;

/**
 * Ответное сообщение на запрос получения отчета по заказам за определенный период от сервиса CreditLine
 * Class CLGetOrderReportResponseMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLGetOrderReportResponseMessage
{
    public OrderReport $Result;

    public function __construct()
    {
        $this->Result = new OrderReport();
    }
}
