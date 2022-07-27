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
    public string $NumOrder;

    /** Информация о клиенте */
    public Client $Client;

    /** Информация о кредите*/
    public Credit $Credit;

    /** Наименование магазина */
    public string $ShopName;

    /** Наличие товара на складе */
    public string $ProductsInStore;

    /** Чьими силами производится подписание КД*/
    public string $SigningKD;

    /** Удобное время для звонка */
    public string $CallTime;
}
