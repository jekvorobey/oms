<?php

namespace App\Models\History;

/**
 * Тип события истории
 * Class HistoryType
 * @package App\Models\History
 */
class HistoryType
{
    /** создание сущности */
    public const TYPE_CREATE = 1;

    /** обновление сущности */
    public const TYPE_UPDATE = 2;

    /** удаление сущности */
    public const TYPE_DELETE = 3;

    /** создание комментария к заказу */
    public const TYPE_COMMENT = 4;

    /** добавление связи одной сущности к другой */
    public const TYPE_CREATE_LINK = 5;

    /** удаление связи одной сущности к другой */
    public const TYPE_DELETE_LINK = 6;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * DeliveryType constructor.
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
}
