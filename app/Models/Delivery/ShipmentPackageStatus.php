<?php

namespace App\Models\Delivery;

/**
 * Статус коробки отправления
 * Class ShipmentStatus
 * @package App\Models\Delivery
 */
class ShipmentPackageStatus
{
    /** @var int - создана (автоматически устанавливается платформой) */
    public const STATUS_CREATED = 1;
    /** @var int - в сборке (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLING = 2;
    /** @var int - собрана (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLED = 3;
    /** @var int - проблема при сборке (устанавливается вручную оператором мерчанта) */
    public const STATUS_ASSEMBLING_PROBLEM = 4;
    /** @var int - сборка просрочена (автоматически устанавливается платформой) */
    public const STATUS_TIMEOUT = 5;
    /** @var int - сборка отменена (устанавливается вручную администратором iBT) */
    public const STATUS_CANCEL = 6;
    /** @var int - утеряна при доставке (автоматически получается от службы доставки) */
    public const STATUS_LOST = 7;
    
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
            new self(self::STATUS_CREATED, 'Создана'),
            new self(self::STATUS_ASSEMBLING, 'В сборке'),
            new self(self::STATUS_ASSEMBLED, 'Собрана'),
            new self(self::STATUS_ASSEMBLING_PROBLEM, 'Проблема при сборке'),
            new self(self::STATUS_TIMEOUT, 'Сборка просрочена'),
            new self(self::STATUS_CANCEL, 'Сборка отменена'),
            new self(self::STATUS_LOST, 'Утеряна при доставке'),
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
            self::STATUS_LOST,
        ];
    }
    
    /**
     * ShipmentStatus constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
