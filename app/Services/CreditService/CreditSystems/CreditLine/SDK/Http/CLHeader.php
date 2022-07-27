<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Class CLHeader
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLHeader
{
    /** Хеш-функция логина Партнера */
    public string $PartnerLogin;

    /** Хеш-функция пароля Партнера */
    public string $PartnerPassword;
}
