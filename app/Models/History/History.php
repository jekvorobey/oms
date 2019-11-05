<?php

namespace App\Models\History;

use App\Models\OmsModel;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Model;

/**
 * Класс-модель для сущности "История изменения сущностей"
 * Class History
 * @package App\Models\History
 *
 * @property int $user_id - id пользователя
 * @property int $type - тип события
 * @property string $data - информация
 * @property string $main_entity - название основной сущности, деталке которой будет выводится история изменения (например, Order или Shipment)
 * @property int $main_entity_id - id основной сущность (например, Заказа или Отправления)
 * @property string $entity - название изменяемой сущности (например, BasketItem или ShipmentItem)
 * @property int $entity_id - id изменяемой сущности (например, Позиция корзины или Позиция отправления)
 */
class History extends OmsModel
{
    /** @var string */
    protected $table = 'history';
    /** @var array */
    protected $casts = [
        'data' => 'array'
    ];
    
    /**
     * @param  int  $type
     * @param  string  $mainEntity
     * @param  Model  $model
     */
    public static function saveEvent(int $type, OmsModel $mainModel, OmsModel $model): void
    {
        $mainModelClass = explode('\\', get_class($mainModel));
        $modelClass = explode('\\', get_class($model));
        
        /** @var RequestInitiator $user */
        $user = resolve(RequestInitiator::class);
        $event = new self();
        $event->type = $type;
        $event->user_id = $user->userId();
        $event->main_entity = end($mainModelClass);
        $event->main_entity_id = $mainModel->id;
        $event->entity_id = $model->id;
        $event->entity = end($modelClass);
        $event->data = $type != HistoryType::TYPE_DELETE ? $model->getDirty() : $model->toArray();
        $event->save();

        if ($mainModel->notificator) {
            $mainModel->notificator::notify($type, $mainModel, $model);
        }
    }
}
