<?php

namespace App\Models\Order;

/**
 * Тип подтверждения заказа
 * Class OrderConfirmationType
 * @package App\Models\Order
 */
class OrderConfirmationType
{
    /** подтвердить заказ по SMS */
    public const SMS = 1;

    /** подтвердить заказ через звонок оператора */
    public const CALL = 2;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * OrderConfirmationType constructor.
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public static function all(): array
    {
        return [
            self::SMS => new self(self::SMS, 'Подтвердить заказ по SMS'),
            self::CALL => new self(
                self::CALL,
                'Подтвердить заказ через звонок оператора'
            ),
        ];
    }

    public static function validValues(): array
    {
        return array_keys(static::all());
    }
}
