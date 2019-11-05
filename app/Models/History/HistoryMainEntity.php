<?php

namespace App\Models\History;

use App\Models\OmsModel;

/**
 * Класс-модель для сущности "Привязка истории изменения сущностей к основным сущностям"
 * Class HistoryMainEntity
 * @package App\Models\History
 *
 * @property int $history_id - id записи в истории
 * @property string $main_entity - название основной сущности, деталке которой будет выводится история изменения (например, Order или Shipment)
 * @property int $main_entity_id - id основной сущность (например, Заказа или Отправления)
 */
class HistoryMainEntity extends OmsModel
{
    /** @var string */
    protected $table = 'history_main_entity';
}
