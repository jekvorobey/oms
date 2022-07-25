<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLResponse;

/**
 * Отчет
 * Class OrderReport
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity
 */
class OrderReport extends CLResponse
{
    /** Отчет в XML */
    public string $report;
}
