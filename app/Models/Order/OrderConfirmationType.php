<?php

namespace App\Models\Order;

/**
 * Тип подтверждения заказа
 * Class OrderConfirmationType
 * @package App\Models\Order
 */
class OrderConfirmationType
{
    /** @var int - подтвердить заказ по SMS */
    public const SMS = 1;
    /** @var int - подтвердить заказ через звонок оператора */
    public const CALL = 2;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * @return array
     */
    public static function all()
    {
        return [
            self::SMS => new self(
                self::SMS,
                'Подтвердить заказ по SMS'
            ),
            self::CALL => new self(
                self::CALL,
                'Подтвердить заказ через звонок оператора'
            ),
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return array_keys(static::all());
    }

    /**
     * OrderConfirmationType constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
