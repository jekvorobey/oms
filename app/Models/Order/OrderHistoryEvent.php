<?php

namespace App\Models\Order;

use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Класс-модель для сущности "история Заказов"
 * Class OrderHistory
 * @package App\Models
 *
 * @property int $id
 * @property int $order_id - id заказа
 * @property int $user_id - id пользователя
 * @property int $type - тип события
 * @property string $data - информация
 * @property int $entity_id
 * @property int $entity
 *
 * @property Order $order - заказ
 */
class OrderHistoryEvent extends Model
{
    public const TYPE_CREATE = 1;
    public const TYPE_UPDATE = 2;
    public const TYPE_DELETE = 3;
    
    protected static $unguarded = true;
    protected $table = 'orders_history';
    protected $casts = [
        'data' => 'array'
    ];
    
    public static function saveEvent(int $type, int $orderId, Model $model)
    {
        $entityClass = get_class($model);
        $classParts = explode('\\', $entityClass);
        /** @var RequestInitiator $user */
        $user = resolve(RequestInitiator::class);
        $event = new self();
        $event->type = $type;
        $event->user_id = $user->userId();
        $event->order_id = $orderId;
        $event->entity_id = $model->id;
        $event->entity = end($classParts);
        if ($type != self::TYPE_DELETE) {
            $event->data = $model->getDirty();
        }
        $event->save();
    }
    
    /**
     * Получить запрос на выборку событий.
     *
     * @param RestQuery $restQuery
     * @return Builder
     */
    public static function findByRest(RestQuery $restQuery): Builder
    {
        $query = self::query();
        foreach ($restQuery->filterIterator() as [$field, $op, $value]) {
            if ($op == '=' && is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $op, $value);
            }
        }
        $pagination = $restQuery->getPage();
        if ($pagination) {
            $query->offset($pagination['offset'])->limit($pagination['limit']);
        }
        return $query;
    }
    
    /**
     * @return HasOne
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }
}
