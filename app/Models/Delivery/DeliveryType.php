<?php

namespace App\Models\Delivery;

/**
 * Тип доставки
 * Class DeliveryType
 * @package App\Models\Delivery
 */
class DeliveryType
{
    /** @var int - несколькими доставками */
    const TYPE_SPLIT = 1;
    /** @var int - одной доставкой */
    const TYPE_CONSOLIDATION = 2;
    
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
            self::TYPE_SPLIT => new self(self::TYPE_SPLIT, 'Несколькими доставками'),
            self::TYPE_CONSOLIDATION => new self(self::TYPE_CONSOLIDATION, 'Одной доставкой'),
        ];
    }
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::TYPE_SPLIT,
            self::TYPE_CONSOLIDATION,
        ];
    }
    
    /**
     * DeliveryType constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
