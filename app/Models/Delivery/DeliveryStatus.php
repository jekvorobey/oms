<?php

namespace App\Models\Delivery;

/**
 * Статус доставки
 * Class DeliveryStatus
 * @package App\Models\Delivery
 */
class DeliveryStatus
{
    //внутренние статусы [0; 20]
    /** предзаказ: ожидаем поступления товара */
    public const PRE_ORDER = 0;
    /** оформлена */
    public const CREATED = 1;
    /** ожидает проверки АОЗ */
    public const AWAITING_CHECK = 2;
    /** проверка АОЗ */
    public const CHECKING = 3;
    /** ожидает подтверждения Мерчантом */
    public const AWAITING_CONFIRMATION = 4;
    /** на комплектации */
    public const ASSEMBLING = 5;
    /** готова к отгрузке */
    public const ASSEMBLED = 6;
    /** передана Логистическому Оператору */
    public const SHIPPED = 7;

    //статусы доставки в случае "нормального" процесса доставки [21; 40]
    /** принята логистическим оператором (принята на склад в пункте отправления) */
    public const ON_POINT_IN = 21;
    /** прибыла в город назначения */
    public const ARRIVED_AT_DESTINATION_CITY = 22;
    /** принята в пункте назначения (принята на складе в пункте назначения) */
    public const ON_POINT_OUT = 23;
    /** находится в Пункте Выдачи (готова к выдаче в пункте назначения) */
    public const READY_FOR_RECIPIENT = 24;
    /** выдана курьеру для доставки (передана на доставку в пункте назначения) */
    public const DELIVERING = 25;
    /** доставлена получателю */
    public const DONE = 26;

    //статусы по отказам и возвратам [41; 60]
    /** ожидается отмена */
    public const CANCELLATION_EXPECTED = 41;
    /** ожидается возврат от клиента */
    public const RETURN_EXPECTED_FROM_CUSTOMER = 42;
    /** возвращена */
    public const RETURNED = 43;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * DeliveryStatus constructor.
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
            //внутренние статусы
            self::CREATED => new self(self::CREATED, 'Оформлена'),
            self::AWAITING_CHECK => new self(self::AWAITING_CHECK, 'Ожидает проверки АОЗ'),
            self::CHECKING => new self(self::CHECKING, 'Проверка АОЗ'),
            self::AWAITING_CONFIRMATION => new self(
                self::AWAITING_CONFIRMATION,
                'Ожидает подтверждения Мерчантом'
            ),
            self::ASSEMBLING => new self(self::ASSEMBLING, 'На комплектации'),
            self::ASSEMBLED => new self(self::ASSEMBLED, 'Готова к отгрузке'),
            self::SHIPPED => new self(
                self::SHIPPED,
                'Передана Логистическому Оператору'
            ),
            self::PRE_ORDER => new self(
                self::PRE_ORDER,
                'Предзаказ: ожидаем поступления товара'
            ),

            //статусы доставки в случае "нормального" процесса доставки
            self::ON_POINT_IN => new self(
                self::ON_POINT_IN,
                'Принята логистическим оператором'
            ),
            self::ARRIVED_AT_DESTINATION_CITY => new self(
                self::ARRIVED_AT_DESTINATION_CITY,
                'Прибыла в город назначения'
            ),
            self::ON_POINT_OUT => new self(self::ON_POINT_OUT, 'Принята в пункте назначения'),
            self::READY_FOR_RECIPIENT => new self(
                self::READY_FOR_RECIPIENT,
                'Находится в Пункте Выдачи'
            ),
            self::DELIVERING => new self(self::DELIVERING, 'Выдана курьеру для доставки'),
            self::DONE => new self(self::DONE, 'Доставлена получателю'),

            //статусы по отказам и возвратам
            self::CANCELLATION_EXPECTED => new self(self::CANCELLATION_EXPECTED, 'Ожидается отмена'),
            self::RETURN_EXPECTED_FROM_CUSTOMER => new self(
                self::RETURN_EXPECTED_FROM_CUSTOMER,
                'Ожидается возврат от клиента'
            ),
            self::RETURNED => new self(self::RETURNED, 'Возвращена'),
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return array_keys(self::all());
    }
}
