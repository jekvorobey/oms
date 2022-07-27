<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity;

/**
 * Товар
 * Class Product
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity
 */
class Product
{
    /** Наименование товара */
    public string $Name;

    /** Цена за единицу товара */
    public float $Price;

    /** Количество единиц товара */
    public int $Count;

    /**
     * Создает объект класса
     * @param string $name Наименование товара
     * @param float $price Цена за единицу товара
     * @param int $count Количество единиц товара
     */
    public function __construct($name, $price, $count)
    {
        $this->Name = $name;
        $this->Price = $price;
        $this->Count = $count;
    }

    /**
     * Возвращает полную сумму группы товаров
     * @return float Сумма товаров
     */
    public function getTotalPrice(): float
    {
        return $this->Price * $this->Count;
    }
}
