<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum\BanksEnum;

/**
 * Class CreditPreferences
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity
 */
class CreditPreferences
{
    /** Предполагаемый первоначальный платеж */
    public float $InitialPayment;

    /** Предполагаемый срок кредита */
    public int $CreditPeriod;

    /** Предполагаемый банк кредитования */
    public string $Bank;

    /** Предполагаемая акция (кредитный продукт) */
    public string $Action;

    /**
     * Создает объект класса
     * @param float|null $initialPayment Предполагаемый первоначальный платеж
     * @param int|null $creditPeriod Предполагаемый срок кредита
     * @param string|null $bank Банк
     * @param string|null $action Предполагаемая акция (кредитный продукт)
     */
    public function __construct(
        ?float $initialPayment = .0,
        ?int $creditPeriod = 0,
        ?string $bank = BanksEnum::NONE,
        ?string $action = ''
    ) {
        $this->InitialPayment = (float) $initialPayment;
        $this->CreditPeriod = (int) $creditPeriod;
        $this->Bank = (string) $bank;
        $this->Action = (string) $action;
    }
}
