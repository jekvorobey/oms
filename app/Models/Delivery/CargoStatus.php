<?php

namespace App\Models\Delivery;

/**
 * Статус груза
 * P.S. Статусы доставки груза от службы доставки мы не получаем,
 * статусы будут передаваться отдельно для заказов на доставку (доставок у нас), которые входят в груз (см. DeliveryStatus)
 * Class CargoStatus
 * @package App\Models\Delivery
 */
class CargoStatus
{
    /** сформирован (автоматически устанавливается платформой) */
    public const CREATED = 1;

    /** передан Логистическому Оператору (устанавливается вручную оператором мерчанта) */
    public const SHIPPED = 2;

    /** принят Логистическим Оператором (автоматически устанавливается платформой из статуса Отправлений) */
    public const TAKEN = 3;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * CargoStatus constructor.
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @return array|self[]
     */
    public static function all()
    {
        return [
            self::CREATED => new self(self::CREATED, 'Создан'),
            self::SHIPPED => new self(self::SHIPPED, 'Передан Логистическому Оператору'),
            self::TAKEN => new self(self::TAKEN, 'Принят Логистическим Оператором'),
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return array_keys(static::all());
    }
}
