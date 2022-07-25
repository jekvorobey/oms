<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Ответное сообщение на запрос проверки организации в системе CreditLine
 * Class CLConfirmOrganizationResponseMessage
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLConfirmOrganizationResponseMessage
{
    /** Активна ли точка (в случае ошибки возвращает false) */
    public bool $isActive;
}
