<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Ответ заявки на кредит
 * Class CreditLineResponse
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLResponse
{
    /** Статус выполнения операции */
    public ?bool $confirm;

    /** Код ошибки (если ошибки нет, то возвращается 0) */
    public ?int $errorCode;

    /** Текст ошибки*/
    public ?string $errorText;
}
