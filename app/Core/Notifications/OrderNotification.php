<?php

namespace App\Core\Notifications;

use App\Models\History\HistoryType;
use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;
use Greensight\Message\Dto\Notification\NotificationDto;
use Illuminate\Database\Eloquent\Model;

/**
 * Уведомления по заказам
 * Class OrderNotification
 * @package App\Core\Notifications
 */
class OrderNotification extends AbstractNotification implements NotificationInterface
{
    /**
     * @param Order $mainModel
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function notify(int $type, Model $mainModel, Model $model): void
    {
        $this->notifyAdmins($type, $mainModel);
    }

    protected function notifyAdmins(int $type, Order $order): void
    {
        $notification = $this->getBaseNotification();

        switch ($type) {
            case HistoryType::TYPE_CREATE:
                $notification->type = NotificationDto::TYPE_ORDER_NEW;
                $notification->setPayloadField('title', 'Новый заказ');
                $notification->setPayloadField('body', "Создан заказ {$order->number}");
                break;
            case HistoryType::TYPE_UPDATE:
                if ($order->is_problem) {
                    $notification->type = NotificationDto::TYPE_ORDER_PROBLEM;
                    $notification->setPayloadField('title', 'Проблемный заказ');
                    $notification->setPayloadField('body', "Заказ {$order->number} помечен как проблемный");
                }
                if ($order->payment_status == PaymentStatus::PAID) {
                    $notification->type = NotificationDto::TYPE_ORDER_PAYED;
                    $notification->setPayloadField('title', 'Оплачен заказ');
                    $notification->setPayloadField('body', "Заказ {$order->number} оплачен");
                }
                if ($order->is_canceled) {
                    $notification->type = NotificationDto::TYPE_ORDER_CANCEL;
                    $notification->setPayloadField('title', 'Отмена заказа');
                    $notification->setPayloadField('body', "Заказ {$order->number} был отменён");
                }
                break;

            case HistoryType::TYPE_COMMENT:
                $notification->type = NotificationDto::TYPE_ORDER_COMMENT;
                $notification->setPayloadField('title', 'Обновлён комментарий заказа');
                $notification->setPayloadField('body', "Комментарий заказа {$order->number} был обновлен");
                break;
        }

        if (!$notification->type) {
            return;
        }

        //todo Добавить создание уведомлений для администраторов
    }
}
