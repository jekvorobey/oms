<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Class CLHeader
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLHeader
{
    /** Хеш-функция логина Партнера */
    public string $partnerLogin;

    /** Хеш-функция пароля Партнера */
    public string $partnerPassword;
}
