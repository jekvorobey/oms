<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Http;

use App\Services\CreditService\CreditSystems\CreditLine\CreditLineSystem;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum\BanksEnum;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum\OrderStatusEnum;

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
    public function getStatusId(): ?int
    {
        switch (mb_strtolower($this->Status, 'UTF-8')) {
            case OrderStatusEnum::ORDER_STATUS_REFUSED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_REFUSED;
            case OrderStatusEnum::ORDER_STATUS_ACCEPTED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_ACCEPTED;
            case OrderStatusEnum::ORDER_STATUS_ANNULED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_ANNULED;
            case OrderStatusEnum::ORDER_STATUS_IN_COMPLETED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_IN_COMPLETED;
            case OrderStatusEnum::ORDER_STATUS_IN_WORK:
                return CreditLineSystem::CREDIT_ORDER_STATUS_IN_WORK;
            case OrderStatusEnum::ORDER_STATUS_IN_STACK:
                return CreditLineSystem::CREDIT_ORDER_STATUS_IN_STACK;
            case OrderStatusEnum::ORDER_STATUS_CASHED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_CASHED;
            case OrderStatusEnum::ORDER_STATUS_IN_CASH:
                return CreditLineSystem::CREDIT_ORDER_STATUS_IN_CASH;
            case OrderStatusEnum::ORDER_STATUS_SIGNED:
                return CreditLineSystem::CREDIT_ORDER_STATUS_SIGNED;
            default:
                return null;
        }
    }

    /**
     * Получить описание статуса
     */
    public function getStatusDescription(): ?string
    {
        switch (mb_strtolower($this->Status, 'UTF-8')) {
            case OrderStatusEnum::ORDER_STATUS_REFUSED:
                return 'Отказ';
            case OrderStatusEnum::ORDER_STATUS_ACCEPTED:
                return 'Одобрение';
            case OrderStatusEnum::ORDER_STATUS_ANNULED:
                return 'Аннулирован';
            case OrderStatusEnum::ORDER_STATUS_IN_COMPLETED:
                return 'Incompleted';
            case OrderStatusEnum::ORDER_STATUS_IN_WORK:
                return 'В работе';
            case OrderStatusEnum::ORDER_STATUS_IN_STACK:
                return 'В очереди на обработку';
            case OrderStatusEnum::ORDER_STATUS_CASHED:
                return 'Оплачен';
            case OrderStatusEnum::ORDER_STATUS_IN_CASH:
                return 'В процессе оплаты';
            case OrderStatusEnum::ORDER_STATUS_SIGNED:
                return 'Подписан';
            default:
                return null;
        }
    }

    public function getBankName(): string
    {
        switch ($this->BankCode) {
            case BanksEnum::HOME_CREDIT_AND_FINANCE_BANK:
                return 'Банк Хоум Кредит';
            case BanksEnum::BANK_RUSSIAN_STANDARD:
                return 'Банк Русский Стандарт';
            case BanksEnum::CREDIT_EUROPE_BANK:
                return 'Банк Кредит Европа';
            case BanksEnum::OTP_BANK:
                return 'OTP Банк';
            case BanksEnum::RENESSANS_CREDIT_BANK:
                return 'Банк Ренессанс Кредит';
            case BanksEnum::ALFA_BANK:
                return 'Альфа Банк';
            case BanksEnum::SETELEM_BANK:
                return 'Сетелем Банк';
            case BanksEnum::MFK_AIR_LOANS:
                return 'МФК ЭйрЛоанс';
            default:
                return null;
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
        return (string) mb_strtolower($this->Status, 'UTF-8');
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
