<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Статус заказа
 * Class CLOrderStatus
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLOrderStatus extends CLResponse
{
    /** Статус */
    public string $status;

    /** Номер заказа в системе Партнера */
    public string $numOrder;

    /**
     * Получить описание статуса
     */
    public function getStatusDescription(): ?string
    {
        switch ($this->status) {
            case 'Refused':
                return 'Отказ';
            case 'Accepted':
                return 'Одобрение';
            case 'Annuled':
                return 'Аннулирован';
            case 'Incompleted':
                return 'Incompleted';
            case 'InWork':
                return 'В работе';
            case 'InStack':
                return 'В очереди на обработку';
            case 'Cashed':
                return 'Оплачен';
            case 'InCash':
                return 'В процессе оплаты';
            case 'Signed':
                return 'Подписан';
            default:
                return '';
        }
    }
}
