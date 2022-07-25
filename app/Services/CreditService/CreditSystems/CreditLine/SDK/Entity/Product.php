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
    public string $name;

    /** Цена за единицу товара */
    public float $price;

    /** Количество единиц товара */
    public int $count;

    /**
     * Создает объект класса
     * @param string $name Наименование товара
     * @param float $price Цена за единицу товара
     * @param int $count Количество единиц товара
     */
    public function __construct($name, $price, $count)
    {
        $this->name = $name;
        $this->price = $price;
        $this->count = $count;
    }

    /**
     * Возвращает полную сумму группы товаров
     * @return float Сумма товаров
     */
    public function getTotalPrice(): float
    {
        return $this->price * $this->count;
    }
}
