<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity;

/**
 * Информация о кредите
 * Class CreditLineCredit
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity
 */
class Credit
{
    /** Размер скидки. Если скидка не указывается, то можно указывать 0 */
    public ?float $discount;

    /** Сумма покупки */
    public ?float $creditSum;

    /** Предпочтения клиента по кредиту */
    public ?CreditPreferences $preference;

    /**
     * Список товаров
     * @var Product[]
     */
    public ?array $products;

    /**
     * Создает объект класса
     * @param float|null $discount Размер скидки. Если скидка не указывается, то можно указывать 0.
     * @param float|null $creditSum Сумма покупки
     * @param Product[]|null $products Список товаров
     * @param CreditPreferences|null $creditPreference Предпочтения клиента по кредиту
     */
    public function __construct(
        ?float $discount,
        ?float $creditSum,
        ?array $products = null,
        ?CreditPreferences $creditPreference = null
    ) {
        $this->discount = (float) $discount;
        $this->creditSum = (float) $creditSum;
        $this->preference = $creditPreference;
        $this->products = $products;
    }
}
