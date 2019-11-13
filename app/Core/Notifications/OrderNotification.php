<?php

namespace App\Core\Notifications;

use App\Models\History\HistoryType;
use App\Models\OmsModel;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Greensight\Message\Dto\Notification\NotificationDto;

/**
 * Уведомления по заказам
 * Class OrderNotification
 * @package App\Core\Notifications
 */
class OrderNotification extends AbstractNotification implements NotificationInterface
{
    /**
     * @inheritDoc
     */
    public static function notify(int $type, OmsModel $mainModel, OmsModel $model): void
    {
        /** @var Order $mainModel */
        static::notifyAdmins($type, $mainModel);
    }
    
    /**
     * @param  int  $type
     * @param  Order  $order
     */
    protected static function notifyAdmins(int $type, Order $order): void
    {
        $notification = static::getBaseNotification();
    
        switch ($type) {
            case HistoryType::TYPE_CREATE:
                $notification->type = NotificationDto::TYPE_ORDER_NEW;
                $notification->payload['title'] = "Новый заказ";
                $notification->payload['body'] = "Создан заказ {$order->number}";
                break;
            case HistoryType::TYPE_UPDATE:
                if($order->is_problem) {
                    $notification->type = NotificationDto::TYPE_ORDER_PROBLEM;
                    $notification->payload['title'] = "Проблемный заказ";
                    $notification->payload['body'] = "Заказ {$order->number} помечен как проблемный";
                }
                if($order->payment_status == PaymentStatus::STATUS_DONE) {
                    $notification->type = NotificationDto::TYPE_ORDER_PAYED;
                    $notification->payload['title'] = "Оплачен заказ";
                    $notification->payload['body'] = "Заказ {$order->number} оплачен";
                }
                if($order->status == OrderStatus::STATUS_CANCEL) {
                    $notification->type = NotificationDto::TYPE_ORDER_CANCEL;
                    $notification->payload['title'] = "Отмена заказа";
                    $notification->payload['body'] = "Заказ {$order->number} был отменён";
                }
                break;
        
            case HistoryType::TYPE_COMMENT:
                $notification->type = NotificationDto::TYPE_ORDER_COMMENT;
                $notification->payload['title'] = "Обновлён комментарий заказа";
                $notification->payload['body'] = "Комментарий заказа {$order->number} был обновлен";
                break;
        }
    
        if(!isset($notification->type)) {
            return;
        }
    
        //todo Добавить создание уведомлений для администраторов
    }
}
