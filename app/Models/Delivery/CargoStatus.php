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
    /** @var int - создан (автоматически устанавливается платформой) */
    public const STATUS_CREATED = 1;
    /** @var int - заявка передана в службу доставки (автоматически устанавливается платформой) */
    public const STATUS_REQUEST_SEND = 2;
    /** @var int - груз передан курьеру (устанавливается вручную оператором мерчанта) */
    public const STATUS_SHIPPED = 3;
    /** @var int - проблема при отгрузке (устанавливается вручную оператором мерчанта) */
    public const STATUS_SHIPPING_PROBLEM = 4;
    /** @var int - отменен  (устанавливается вручную администратором iBT) */
    public const STATUS_CANCEL = 5;
    
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
            new self(self::STATUS_CREATED, 'Создан'),
            new self(self::STATUS_REQUEST_SEND, 'Заявка передана в службу доставки'),
            new self(self::STATUS_SHIPPED, 'Груз передан курьеру'),
            new self(self::STATUS_SHIPPING_PROBLEM, 'Проблема при отгрузке'),
            new self(self::STATUS_CANCEL, 'Отменен'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_REQUEST_SEND,
            self::STATUS_SHIPPED,
            self::STATUS_SHIPPING_PROBLEM,
            self::STATUS_CANCEL,
        ];
    }
    
    /**
     * CargoStatus constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
