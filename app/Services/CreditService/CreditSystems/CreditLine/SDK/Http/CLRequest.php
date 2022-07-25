<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Client;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Credit;

/**
 * Заявка на кредит
 * Class CreditLineRequest
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLRequest
{
    /** Номер заказа в системе Партнёра */
    public string $numOrder;

    /** Информация о клиенте */
    public Client $client;

    /** Информация о кредите*/
    public Credit $credit;

    /** Наименование магазина */
    public string $shopName;

    /** Наличие товара на складе */
    public string $productsInStore;

    /** Чьими силами производится подписание КД*/
    public string $signingKD;

    /** Удобное время для звонка */
    public string $callTime;
}
