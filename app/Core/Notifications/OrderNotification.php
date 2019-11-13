<?php

namespace App\Core\Notifications;

use App\Models\Basket\Basket;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Message\Dto\Notification\NotificationDto;
use Greensight\Message\Services\NotificationService\NotificationService;
use MerchantManagement\Services\OperatorService\OperatorService;
use Pim\Services\OfferService\OfferService;

/**
 * Уведомления по заказам
 * Class OrderNotification
 * @package App\Core\Notifications
 */
class OrderNotification implements NotificationInterface
{
    /**
     * @inheritDoc
     * @throws \Pim\Core\PimException
     */
    public static function notify(int $type, OmsModel $mainModel, OmsModel $model): void
    {
        $dto = [
            'status' => 0,
            'payload' => [
                'type' => '',
                'title' => '',
                'body' => ''
            ]
        ];

        switch ($type) {
            case HistoryType::TYPE_CREATE:
                $dto['type'] = NotificationDto::TYPE_ORDER_NEW;
                $dto['payload']['title'] = "Новый заказ";
                $dto['payload']['body'] = "Создан заказ {$mainModel->number}";
                break;
            case HistoryType::TYPE_UPDATE:
                if($mainModel->is_problem) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_PROBLEM;
                    $dto['payload']['title'] = "Проблемный заказ";
                    $dto['payload']['body'] = "Заказ {$mainModel->number} помечен как проблемный";
                }
                if($mainModel->payment_status == PaymentStatus::STATUS_DONE) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_PAYED;
                    $dto['payload']['title'] = "Оплачен заказ";
                    $dto['payload']['body'] = "Заказ {$mainModel->number} оплачен";
                }
                if($mainModel->status == OrderStatus::STATUS_CANCEL) {
                    $dto['type'] = NotificationDto::TYPE_ORDER_CANCEL;
                    $dto['payload']['title'] = "Отмена заказа";
                    $dto['payload']['body'] = "Заказ {$mainModel->number} был отменён";
                }
                break;

            case HistoryType::TYPE_COMMENT:
                $dto['type'] = NotificationDto::TYPE_ORDER_COMMENT;
                $dto['payload']['title'] = "Обновлён комментарий заказа";
                $dto['payload']['body'] = "Комментарий заказа {$mainModel->number} был обновлен";
                break;
        }

        if(!isset($dto['type'])) return;

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        /** @var OperatorService $operatorService */
        $operatorService = resolve(OperatorService::class);
        $notificationService = resolve(NotificationService::class);

        // Получаем корзину и офферы из корзины заказа
        /** @var Basket $basket */
        $basket = $mainModel->basket->get()->first();
        $basketItems = $basket->items()->get()->pluck('offer_id')->toArray();
        $basketItems = array_unique($basketItems);

        // Получаем id мерчантов, которым принадлежат данные офферы
        $offerQuery = $offerService->newQuery();
        $offerQuery->setFilter('id', $basketItems);
        $merchantIds = $offerService->offers($offerQuery)->pluck('merchant_id')->toArray();
        $merchantIds = array_unique($merchantIds);

        // Получаем id юзеров и операторов выбранных мерчантов
        /** @var RestQuery $operatorQuery */
        $operatorQuery = $operatorService->newQuery();
        $operatorQuery->setFilter('merchant_id', $merchantIds);
        $operatorsIds = $operatorService->operators($operatorQuery)->pluck('user_id')->toArray();
        $operatorsIds = array_unique($operatorsIds);

        // Создаем уведомления
        foreach ($operatorsIds as $userId) {
            $dto['user_id'] = $userId;
            $notificationService->create(new NotificationDto($dto));
        }
    }
}
