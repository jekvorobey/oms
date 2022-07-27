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
    public ?bool $Confirm = null;

    /** Код ошибки (если ошибки нет, то возвращается 0) */
    public ?int $ErrorCode = null;

    /** Текст ошибки*/
    public ?string $ErrorText = null;
}
