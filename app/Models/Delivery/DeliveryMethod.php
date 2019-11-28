<?php

namespace App\Models\Delivery;

/**
 * Способ доставки на последней миле (доставка до места получения заказа)
 * Class DeliveryMethod
 * @package App\Models\Delivery
 * @deprecated Класс переехал в сервис ibt-logistics-ms
 */
class DeliveryMethod
{
    /** @var int - самовывоз из ПВЗ */
    public const METHOD_OUTPOST_PICKUP = 1;
    /** @var int - самовывоз из постомата */
    public const METHOD_POSTOMAT_PICKUP = 2;
    /** @var int - доставка */
    public const METHOD_DELIVERY = 3;
    
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
            new self(self::METHOD_OUTPOST_PICKUP, 'Самовывоз из ПВЗ'),
            new self(self::METHOD_POSTOMAT_PICKUP, 'Самовывоз из постомата'),
            new self(self::METHOD_DELIVERY, 'Доставка'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::METHOD_OUTPOST_PICKUP,
            self::METHOD_POSTOMAT_PICKUP,
            self::METHOD_DELIVERY,
        ];
    }
    
    /**
     * DeliveryMethod constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
