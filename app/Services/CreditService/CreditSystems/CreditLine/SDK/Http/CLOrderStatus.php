<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

/**
 * Статус заказа
 * Class CLOrderStatus
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Http
 */
class CLOrderStatus extends CLResponse
{
    /** Банк */
    public ?string $BankCode = null;

    /** Признак одобрения */
    public ?bool $Confirm = null;

    /** Скидка */
    public ?string $Discount = null;

    /** Сумма оплаты */
    public ?string $InitPay = null;

    /** Номер заказа в системе Партнера */
    public ?string $NumOrder = null;

    /** Статус */
    public ?string $Status = null;

    /** Код ошибки */
    public ?int $ErrorCode = null;

    /** Текст ошибки */
    public ?string $ErrorText = null;

    /**
     * Получить описание статуса
     */
    public function getStatusDescription(): ?string
    {
        switch ($this->Status) {
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

    public function getBankCode(): string
    {
        return (string) $this->BankCode;
    }

    public function getConfirm(): bool
    {
        return (bool) $this->Confirm;
    }

    public function getDiscount(): float
    {
        return (float) str_replace(',', '.', $this->Discount);
    }

    public function getInitPay(): float
    {
        return (float) str_replace(',', '.', $this->InitPay);
    }

    public function getNumOrder(): string
    {
        return (string) $this->NumOrder;
    }

    public function getStatus(): string
    {
        return (string) $this->Status;
    }

    public function getErrorCode(): int
    {
        return (int) $this->ErrorCode;
    }

    public function getErrorText(): string
    {
        return (string) $this->ErrorText;
    }
}
