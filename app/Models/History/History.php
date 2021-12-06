<?php

namespace App\Models\History;

use App\Models\WithMainHistory;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Класс-модель для сущности 'История изменения сущностей'",
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         description="id пользователя"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="integer",
 *         description="тип события"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="string",
 *         description="информация"
 *     ),
 *     @OA\Property(
 *         property="entity_type",
 *         type="string",
 *         description="название изменяемой сущности (например, BasketItem или ShipmentItem)"
 *     ),
 *     @OA\Property(
 *         property="entity_id",
 *         type="integer",
 *         description="id изменяемой сущности (например, Позиция корзины или Позиция отправления)"
 *     ),
 * )
 *
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
class History extends AbstractModel
{
    /** @var string */
    protected $table = 'history';

    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $casts = [
        'data' => 'array',
    ];

    public function historyMainEntities(): HasMany
    {
        return $this->hasMany(HistoryMainEntity::class);
    }

    /**
     * @param Model|Model[] $mainModels Привязываем событие к основным сущностям, на деталке которых оно будет выводится в истории изменения
     * @param Model|WithMainHistory $model
     */
    public static function saveEvent(int $type, $mainModels, Model $model): void
    {
        /** @var RequestInitiator $user */
        $user = resolve(RequestInitiator::class);
        $event = new self();
        $event->type = $type;
        $event->user_id = $user->userId();

        $event->entity_id = $model->getKey();
        $event->entity_type = class_basename($model);
        $event->data = $type !== HistoryType::TYPE_DELETE ? $model->getDirty() : $model->toArray();
        $event->save();

        //Привязываем событие к основным сущностям, деталке которых оно будет выводится в истории изменения
        $mainModels = Arr::wrap($mainModels);

        /** @var Model|WithMainHistory $mainModel */
        foreach ($mainModels as $mainModel) {
            if (!$mainModel) {
                continue;
            }

            $historyMainEntity = new HistoryMainEntity();
            $historyMainEntity->history_id = $event->id;
            $historyMainEntity->main_entity_id = $mainModel->id;
            $historyMainEntity->main_entity_type = class_basename($mainModel);
            $historyMainEntity->save();

            optional($mainModel->historyNotificator())->notify($type, $mainModel, $model);
        }
    }
}
