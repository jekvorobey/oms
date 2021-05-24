<?php

namespace App\Core\Notifications;

use App\Models\Delivery\Shipment;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Message\Dto\Notification\NotificationDto;
use Greensight\Message\Services\NotificationService\NotificationService;
use MerchantManagement\Services\OperatorService\OperatorService;

/**
 * Уведомления по отправлениям
 * Class ShipmentNotification
 * @package App\Core\Notifications
 */
class ShipmentNotification extends AbstractNotification implements NotificationInterface
{
    /**
     * @param Shipment $mainModel
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public static function notify(int $type, OmsModel $mainModel, OmsModel $model): void
    {
        self::notifyMerchants($type, $mainModel);
        self::notifyAdmins($type, $mainModel);
    }

    protected static function notifyMerchants(int $type, Shipment $shipment): void
    {
        $notification = static::getBaseNotification();

        switch ($type) {
            case HistoryType::TYPE_CREATE:
                $notification->type = NotificationDto::TYPE_SHIPMENT_NEW;
                $notification->setPayloadField('title', 'Новый заказ');
                $notification->setPayloadField('body', "Создан заказ {$shipment->number}");
                break;
            case HistoryType::TYPE_UPDATE:
                if ($shipment->is_canceled && $shipment->getOriginal('is_canceled') != $shipment->is_canceled) {
                    $notification->type = NotificationDto::TYPE_SHIPMENT_CANCEL;
                    $notification->setPayloadField('title', 'Отмена заказа');
                    $notification->setPayloadField('body', "Заказ {$shipment->number} был отменён");
                }
                break;
        }

        if (!$notification->type) {
            return;
        }

        /** @var OperatorService $operatorService */
        $operatorService = resolve(OperatorService::class);
        /** @var NotificationService $notificationService */
        $notificationService = resolve(NotificationService::class);

        // Получаем id юзеров и операторов выбранных мерчантов
        /** @var RestQuery $operatorQuery */
        $operatorQuery = $operatorService->newQuery();
        $operatorQuery->setFilter('merchant_id', $shipment->merchant_id);
        $operatorsIds = $operatorService->operators($operatorQuery)->pluck('user_id')->toArray();
        $operatorsIds = array_unique($operatorsIds);

        // Создаем уведомления
        foreach ($operatorsIds as $userId) {
            $notification->user_id = $userId;
            $notificationService->create($notification);
        }
    }

    protected static function notifyAdmins(int $type, Shipment $shipment): void
    {
        $notification = static::getBaseNotification();

        switch ($type) {
            case HistoryType::TYPE_UPDATE:
                if ($shipment->is_problem && $shipment->getOriginal('is_problem') != $shipment->is_problem) {
                    $notification->type = NotificationDto::TYPE_SHIPMENT_PROBLEM;
                    $notification->setPayloadField('title', 'Проблема при сборке отправления');
                    $notification->setPayloadField(
                        'body',
                        "Возникла проблема при сборке отправления {$shipment->number} " .
                        "из заказа {$shipment->delivery->order->number}: {$shipment->assembly_problem_comment}"
                    );
                }
        }

        if (!isset($notification->type)) {
            return;
        }

        //todo Добавить создание уведомлений для администраторов
    }
}
