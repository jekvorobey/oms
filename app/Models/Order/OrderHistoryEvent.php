<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use App\Models\Payment\PaymentStatus;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Greensight\Message\Services\NotificationService\NotificationService;
use MerchantManagement\Services\OperatorService\OperatorService;
use Pim\Services\OfferService\OfferService;
use Greensight\Message\Dto\Notification\NotificationDto;

/**
 * Класс-модель для сущности "история Заказов"
 * Class OrderHistory
 * @package App\Models
 *
 * @property int $order_id - id заказа
 * @property int $user_id - id пользователя
 * @property int $type - тип события
 * @property string $data - информация
 * @property int $entity_id
 * @property int $entity
 *
 * @property Order $order - заказ
 */
class OrderHistoryEvent extends OmsModel
{
    public const TYPE_CREATE = 1;
    public const TYPE_UPDATE = 2;
    public const TYPE_DELETE = 3;
    public const TYPE_COMMENT = 4;

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

        self::createNotifications($type, $model);
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

    private static function createNotifications(int $type, Model $model)
    {
        $dto = [
            'status' => 0,
            'payload' => [
                'url' => '',
                'title' => '',
                'body' => ''
            ]
        ];

        switch ($type) {
            case self::TYPE_CREATE:
                $dto['type'] = NotificationDto::TYPE_ORDER_NEW;
                $dto['payload']['title'] = "Новый заказ";
                $dto['payload']['body'] = "Создан заказ {$model->number}";
                break;
            case self::TYPE_UPDATE:
                if($model->status == OrderStatus::STATUS_PROBLEM) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_PROBLEM;
                    $dto['payload']['title'] = "Проблемный заказ";
                    $dto['payload']['body'] = "Заказ {$model->number} помечен как проблемный";
                }
                if($model->payment_status == PaymentStatus::STATUS_DONE) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_PAYED;
                    $dto['payload']['title'] = "Оплачен заказ";
                    $dto['payload']['body'] = "Заказ {$model->number} оплачен";
                }
                if($model->status == OrderStatus::STATUS_CANCEL) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_CANCEL;
                    $dto['payload']['title'] = "Отмена заказа";
                    $dto['payload']['body'] = "Заказ {$model->number} был отменён";
                }
                break;

            case self::TYPE_COMMENT:
                $dto['type'] = NotificationDto::TYPE_ORDER_COMMENT;
                $dto['payload']['title'] = "Обновлён комментарий заказа";
                $dto['payload']['body'] = "Комментарий заказа {$model->number} был обновлен";
                break;
        }

        if(!isset($dto['type'])) return;

        $offerService = resolve(OfferService::class);
        $operatorService = resolve(OperatorService::class);
        $notificationService = resolve(NotificationService::class);

        // Получаем корзину и офферы из корзины заказа
        $basket = $model->basket()->get()->first();
        $basketItems = $basket->items()->get()->pluck('offer_id')->toArray();

        // Получаем id мерчантов, которым принадлежат данные офферы
        $offerQuery = $offerService->newQuery();
        $offerQuery->setFilter('id', $basketItems);
        $merchantIds = $offerService->offers($offerQuery)->pluck('merchant_id')->toArray();

        // Получаем id юзеров и операторов выбранных мерчантов
        $operatorQuery = $operatorService->newQuery();
        $operatorQuery->setFilter('merchant_id', $merchantIds);
        $operatorsIds = $operatorService->operators($operatorQuery)->pluck('user_id')->toArray();
        $usersIds = $operatorsIds;

        // Создаем уведомления
        foreach ($usersIds as $userId) {
            $dto['user_id'] = $userId;
            $notificationService->create(new NotificationDto($dto));
        }
    }
}
