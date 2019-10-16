<?php

namespace App\Models\Delivery;

/**
 * Статус отправления
 * P.S. Статусы отправления от службы доставки мы не получаем,
 * статусы будут передаваться отдельно для заказов на доставку (доставок у нас), который содержит отправления
 * Class ShipmentStatus
 * @package App\Models
 */
class ShipmentStatus
{
    /** @var int - создано (автоматически устанавливается платформой) */
    public const STATUS_CREATED = 1;
    /** @var int - в сборке (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLING = 2;
    /** @var int - собрано (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLED = 3;
    /** @var int - проблема при сборке (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLING_PROBLEM = 4;
    /** @var int - просрочено  (автоматически устанавливается платформой) */
    public const STATUS_TIMEOUT = 5;
    /** @var int - отменено  (устанавливается вручную администратором iBT) */
    public const STATUS_CANCEL = 6;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    
    /**
     * @return array|self[]
     */
    public static function all()
    {
        return [
            new self(self::STATUS_CREATED, 'Создано'),
            new self(self::STATUS_ASSEMBLING, 'В сборке'),
            new self(self::STATUS_ASSEMBLED, 'Собрано'),
            new self(self::STATUS_ASSEMBLING_PROBLEM, 'Проблема при сборке'),
            new self(self::STATUS_TIMEOUT, 'Просрочено'),
            new self(self::STATUS_CANCEL, 'Отменено'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_ASSEMBLING,
            self::STATUS_ASSEMBLED,
            self::STATUS_ASSEMBLING_PROBLEM,
            self::STATUS_TIMEOUT,
            self::STATUS_CANCEL,
        ];
    }
    
    /**
     * DeliveryService constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
