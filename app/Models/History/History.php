<?php

namespace App\Models\History;

use App\Models\OmsModel;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "История изменения сущностей"
 * Class History
 * @package App\Models\History
 *
 * @property int $user_id - id пользователя
 * @property int $type - тип события
 * @property string $data - информация
 * @property string $entity_type - название изменяемой сущности (например, BasketItem или ShipmentItem)
 * @property int $entity_id - id изменяемой сущности (например, Позиция корзины или Позиция отправления)
 *
 * @property Collection|HistoryMainEntity[] $historyMainEntities
 */
class History extends OmsModel
{
    /** @var string */
    protected $table = 'history';
    /** @var array */
    protected $casts = [
        'data' => 'array',
    ];

    public function historyMainEntities(): HasMany
    {
        return $this->hasMany(HistoryMainEntity::class);
    }

    /**
     * @param OmsModel|array $mainModels
     * @param OmsModel|null $model
     */
    public static function saveEvent(int $type, $mainModels, OmsModel $model): void
    {
        //Сохраняем событие в историю
        $modelClass = explode('\\', get_class($model));
        /** @var RequestInitiator $user */
        $user = resolve(RequestInitiator::class);
        $event = new self();
        $event->type = $type;
        $event->user_id = $user->userId();

        $event->entity_id = $model->id;
        $event->entity_type = end($modelClass);
        $event->data = $type != HistoryType::TYPE_DELETE ? $model->getDirty() : $model->toArray();
        $event->save();

        //Привязываем событие к основным сущностям, деталке которых оно будет выводится в истории изменения
        if (!is_array($mainModels)) {
            $mainModels = [$mainModels];
        }
        foreach ($mainModels as $mainModel) {
            $historyMainEntity = new HistoryMainEntity();
            $historyMainEntity->history_id = $event->id;
            $mainModelClass = explode('\\', get_class($mainModel));
            $historyMainEntity->main_entity_type = end($mainModelClass);
            $historyMainEntity->main_entity_id = $mainModel->id;
            $historyMainEntity->save();

            if ($mainModel->notificator) {
                $mainModel->notificator::notify($type, $mainModel, $model);
            }
        }
    }
}
