<?php

namespace App\Models\Delivery;

/**
 * Служба доставки на последней миле (доставка от распределительного центра до места получения заказа)
 * Class DeliveryService
 * @package App\Models\Delivery
 */
class DeliveryService
{
    /** @var int - B2Cpl */
    public const SERVICE_B2CPL = 1;
    /** @var int - Boxberry */
    public const SERVICE_BOXBERRY = 2;
    /** @var int - СДЭК */
    public const SERVICE_CDEK = 3;
    /** @var int - Dostavista */
    public const SERVICE_DOSTAVISTA = 4;
    /** @var int - DPD */
    public const SERVICE_DPD = 5;
    /** @var int - IML */
    public const SERVICE_IML = 6;
    /** @var int - MaxiPost */
    public const SERVICE_MAXIPOST = 7;
    /** @var int - PickPoint */
    public const SERVICE_PICKPOINT = 8;
    /** @var int - PONY EXPRESS */
    public const SERVICE_PONY_EXPRESS = 9;
    /** @var int - Почта России */
    public const SERVICE_RU_POST = 10;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string - идентификатор службы в интеграторе служб доставки*/
    public $xml_id;
    
    /**
     * @return array|self[]
     */
    public static function all()
    {
        return [
            new self(self::SERVICE_B2CPL, 'B2Cpl', 'b2cpl'),
            new self(self::SERVICE_BOXBERRY, 'Boxberry', 'boxberry'),
            new self(self::SERVICE_CDEK, 'СДЭК', 'cdek'),
            new self(self::SERVICE_DOSTAVISTA, 'Dostavista', 'dostavista'),
            new self(self::SERVICE_DPD, 'DPD', 'dpd'),
            new self(self::SERVICE_IML, 'IML', 'iml'),
            new self(self::SERVICE_MAXIPOST, 'MaxiPost', 'maxi'),
            new self(self::SERVICE_PICKPOINT, 'PickPoint', 'pickpoint'),
            new self(self::SERVICE_PONY_EXPRESS, 'PONY EXPRESS', 'pony'),
            new self(self::SERVICE_RU_POST, 'Почта России', 'rupost'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues()
    {
        return [
            self::SERVICE_B2CPL,
            self::SERVICE_BOXBERRY,
            self::SERVICE_CDEK,
            self::SERVICE_DOSTAVISTA,
            self::SERVICE_DPD,
            self::SERVICE_IML,
            self::SERVICE_MAXIPOST,
            self::SERVICE_PICKPOINT,
            self::SERVICE_PONY_EXPRESS,
            self::SERVICE_RU_POST,
        ];
    }
    
    /**
     * DeliveryService constructor.
     * @param  int  $id
     * @param  string  $name
     * @param  string  $xml_id - идентификатор службы в интеграторе служб доставки
     */
    public function __construct(int $id, string $name, string $xml_id)
    {
        $this->id = $id;
        $this->name = $name;
        $this->xml_id = $xml_id;
    }
}
