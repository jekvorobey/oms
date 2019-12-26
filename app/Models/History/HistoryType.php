<?php

namespace App\Models\History;

/**
 * Тип события истории
 * Class HistoryType
 * @package App\Models\History
 */
class HistoryType
{
    /** @var int - создание сущности */
    const TYPE_CREATE = 1;
    /** @var int - обновление сущности */
    const TYPE_UPDATE = 2;
    /** @var int - удаление сущности */
    const TYPE_DELETE = 3;
    /** @var int - создание комментария к заказу */
    const TYPE_COMMENT = 4;
    /** @var int - добавление связи одной сущности к другой */
    const TYPE_CREATE_LINK = 5;
    /** @var int - удаление связи одной сущности к другой */
    const TYPE_DELETE_LINK = 6;
    
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
            new self(self::TYPE_CREATE, 'Создание сущности'),
            new self(self::TYPE_UPDATE, 'Обновление сущности'),
            new self(self::TYPE_DELETE, 'Удаление сущности'),
            new self(self::TYPE_COMMENT, 'Создание комментария к заказу'),
            new self(self::TYPE_CREATE_LINK, 'Добавление связи одной сущности к другой'),
            new self(self::TYPE_DELETE_LINK, 'Удаление связи одной сущности к другой'),
        ];
    }
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::TYPE_CREATE,
            self::TYPE_UPDATE,
            self::TYPE_DELETE,
            self::TYPE_COMMENT,
            self::TYPE_CREATE_LINK,
            self::TYPE_DELETE_LINK,
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
