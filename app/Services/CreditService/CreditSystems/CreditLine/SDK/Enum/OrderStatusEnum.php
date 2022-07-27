<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum;

/**
 * Перечисление статусов заявок на кредит
 * Class OrderStatusEnum
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum
 */
abstract class OrderStatusEnum
{
    public const ORDER_STATUS_REFUSED = 'refused';
    public const ORDER_STATUS_ACCEPTED = 'accepted';
    public const ORDER_STATUS_ANNULED = 'annuled';
    public const ORDER_STATUS_IN_COMPLETED = 'incompleted';
    public const ORDER_STATUS_IN_WORK = 'inWork';
    public const ORDER_STATUS_IN_STACK = 'inStack';
    public const ORDER_STATUS_IN_CASH = 'inCash';
    public const ORDER_STATUS_CASHED = 'cashed';
    public const ORDER_STATUS_SIGNED = 'signed';
}
