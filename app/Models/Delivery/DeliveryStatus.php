<?php

namespace App\Models\Delivery;

/**
 * Статус доставки
 * Class DeliveryStatus
 * @package App\Models\Delivery
 */
class DeliveryStatus
{
    //внутренние статусы [1; 10]
    /** @var int - создан */
    public const STATUS_CREATED = 1;
    
    //статусы, связанные с передачей информации по сущности во внешнюю систему [11; 20]
    /** @var int - загрузка информации в систему перевозчика */
    public const STATUS_UPLOADING = 11;
    /** @var int - информация успешно загружена в систему перевозчика */
    public const STATUS_UPLOADED = 12;
    /** @var int - ошибка передачи информации в систему перевозчика */
    public const STATUS_UPLOADING_ERROR = 13;
    
    //статусы доставки в случае "нормального" процесса доставки [21; 40]
    /** @var int - принят на склад в пункте отправления */
    public const STATUS_ON_POINT_IN = 21;
    /** @var int - в пути */
    public const STATUS_ON_WAY = 22;
    /** @var int - прибыл на склад в пункте назначения */
    public const STATUS_ON_POINT_OUT = 23;
    /** @var int - передана на доставку в пункте назначения */
    public const STATUS_DELIVERING = 24;
    /** @var int - готов к выдаче в пункте назначения */
    public const STATUS_READY_FOR_RECIPIENT = 25;
    /** @var int - доставлен получателю */
    public const STATUS_DONE = 26;
    
    //статусы по возвратам [41; 60]
    /** @var int - возвращен с доставки */
    public const STATUS_RETURNED_FROM_DELIVERY = 41;
    /** @var int - частичный возврат */
    public const STATUS_PARTIAL_RETURN = 42;
    /** @var int - подготовлен возврат */
    public const STATUS_RETURN_READY = 43;
    /** @var int - возвращается отправителю */
    public const STATUS_RETURNING = 44;
    /** @var int - возвращен отправителю */
    public const STATUS_RETURNED = 45;
    
    //проблемные и отмененные статусы [61; 80]
    /** @var int - утеряна */
    public const STATUS_LOST = 61;
    /** @var int - отменена */
    public const STATUS_PROBLEM = 62;
    /** @var int - возникла проблема */
    public const STATUS_CANCEL = 63;
    
    //нестандартные статусы [91; 100]
    /** @var int - неизвестный статус */
    public const STATUS_UNKNOWN = 91;
    /** @var int - n/a */
    public const STATUS_NA = 92;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string - идентификатор статуса в интеграторе служб доставки*/
    public $xml_id;
    
    /**
     * @return array|self[]
     */
    public static function all()
    {
        return [
            //внутренние статусы
            new self(self::STATUS_CREATED, 'Создан'),
            
            //статусы, связанные с передачей информации по сущности во внешнюю систему
            new self(self::STATUS_UPLOADING, 'Загрузка информации в систему перевозчика', 'uploading'),
            new self(self::STATUS_UPLOADED, 'Информация успешно загружена в систему перевозчика', 'uploaded'),
            new self(self::STATUS_UPLOADING_ERROR, 'Ошибка передачи информации в систему перевозчика', 'uploadingError'),
            
            //статусы доставки в случае "нормального" процесса доставки
            new self(self::STATUS_ON_POINT_IN, 'Принят на склад в пункте отправления', 'onPointIn'),
            new self(self::STATUS_ON_WAY, 'В пути', 'onWay'),
            new self(self::STATUS_ON_POINT_OUT, 'Прибыл на склад в пункте назначения', 'onPointOut'),
            new self(self::STATUS_DELIVERING, 'Передан на доставку в пункте назначения', 'delivering'),
            new self(self::STATUS_READY_FOR_RECIPIENT, 'Готов к выдаче в пункте назначения', 'readyForRecipient'),
            new self(self::STATUS_DONE, 'Доставлен получателю', 'delivered'),
            
            //статусы по возвратам
            new self(self::STATUS_RETURNED_FROM_DELIVERY, 'Возвращен с доставки', 'returnedFromDelivery'),
            new self(self::STATUS_PARTIAL_RETURN, 'Частичный возврат', 'partialReturn'),
            new self(self::STATUS_RETURN_READY, 'Подготовлен возврат', 'returnReady'),
            new self(self::STATUS_RETURNING, 'Возвращается отправителю', 'returning'),
            new self(self::STATUS_RETURNED, 'Возвращен отправителю', 'returned'),
            
            //проблемные и отмененные статусы
            new self(self::STATUS_LOST, 'Утеряна', 'lost'),
            new self(self::STATUS_PROBLEM, 'Возникла проблема', 'problem'),
            new self(self::STATUS_CANCEL, 'Отменена', 'deliveryCanceled'),
            
            //нестандартные статусы
            new self(self::STATUS_UNKNOWN, 'Неизвестный статус', 'unknown'),
            new self(self::STATUS_NA, 'N/A', 'notApplicable'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::STATUS_CREATED,
            
            self::STATUS_UPLOADING,
            self::STATUS_UPLOADED,
            self::STATUS_UPLOADING_ERROR,
            
            self::STATUS_ON_POINT_IN,
            self::STATUS_ON_WAY,
            self::STATUS_ON_POINT_OUT,
            self::STATUS_DELIVERING,
            self::STATUS_READY_FOR_RECIPIENT,
            self::STATUS_DONE,
            
            self::STATUS_RETURNED_FROM_DELIVERY,
            self::STATUS_PARTIAL_RETURN,
            self::STATUS_RETURN_READY,
            self::STATUS_RETURNING,
            self::STATUS_RETURNED,
            
            self::STATUS_LOST,
            self::STATUS_PROBLEM,
            self::STATUS_CANCEL,
            
            self::STATUS_UNKNOWN,
            self::STATUS_NA,
        ];
    }
    
    /**
     * DeliveryStatus constructor.
     * @param  int  $id
     * @param  string  $name
     * @param  string  $xml_id
     */
    public function __construct(int $id, string $name, string $xml_id = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->xml_id = $xml_id;
    }
}
