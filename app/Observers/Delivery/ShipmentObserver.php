<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\HistoryType;
use App\Services\DeliveryService;
use App\Services\DeliveryServiceInvalidConditions;
use App\Services\OrderService;
use App\Services\ShipmentPackageService;
use App\Services\ShipmentService;
use Exception;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Support\Str;
use MerchantManagement\Dto\OperatorCommunicationMethod;
use MerchantManagement\Services\MerchantService\MerchantService;
use MerchantManagement\Services\OperatorService\OperatorService;
use Greensight\Logistics\Dto\Lists\DeliveryService as DeliveryServiceDto;

/**
 * Class ShipmentObserver
 * @package App\Observers\Delivery
 */
class ShipmentObserver
{
    protected const ELIGIBLE_STATUS = [
        ShipmentStatus::CREATED,
        ShipmentStatus::AWAITING_CONFIRMATION,
    ];

    /**
     * Автоматическая установка статуса для доставки, если все её отправления получили нужный статус
     */
    protected const STATUS_TO_DELIVERY = [
        ShipmentStatus::AWAITING_CONFIRMATION => DeliveryStatus::AWAITING_CONFIRMATION,
        ShipmentStatus::ASSEMBLING => DeliveryStatus::ASSEMBLING,
        ShipmentStatus::ASSEMBLED => DeliveryStatus::ASSEMBLED,
        ShipmentStatus::SHIPPED => DeliveryStatus::SHIPPED,
        ShipmentStatus::ON_POINT_IN => DeliveryStatus::ON_POINT_IN,
    ];

    static private array $createdDeliveryOrders= [];

    public function creating(Shipment $shipment): void
    {
        $shipment->guid = (string) Str::uuid();
    }

    /**
     * Handle the shipment "updating" event.
     */
    public function updating(Shipment $shipment): bool
    {
        return $this->checkAllProductsPacked($shipment);
    }

    /**
     * Handle the shipment "updated" event.
     * @return void
     * @throws Exception
     */
    public function updated(Shipment $shipment)
    {
        $this->setCheckingStatus($shipment);
        $this->setStatusToDelivery($shipment);
        $this->setIsCanceledToDelivery($shipment);
        $this->setIsCanceledToBasketItems($shipment);
        $this->setOrderIsPartiallyCancelled($shipment);
        $this->setTakenStatusToCargo($shipment);
        $this->sendStatusNotification($shipment);
    }

    /**
     * Handle the shipment "deleting" event.
     * @throws Exception
     */
    public function deleting(Shipment $shipment)
    {
        foreach ($shipment->packages as $package) {
            $package->delete();
        }
    }

    /**
     * Handle the shipment "deleted" event.
     * @throws Exception
     */
    public function deleted(Shipment $shipment)
    {
        if ($shipment->cargo_id) {
            $shipment->cargo->recalc();
        }
        $shipment->delivery->recalc();
    }

    /**
     * Handle the order "saving" event.
     * @return void
     */
    public function saving(Shipment $shipment)
    {
        if (!$shipment->guid) {
            $shipment->guid = (string) Str::uuid();
        }

        $this->setStatusAt($shipment);
        $this->setPaymentStatusAt($shipment);
        $this->setProblemAt($shipment);
        $this->setCanceledAt($shipment);
        $this->setFsd($shipment);
    }

    /**
     * Handle the shipment "saved" event.
     * @throws Exception
     */
    public function saved(Shipment $shipment)
    {
        $this->recalcCargoAndDeliveryOnSaved($shipment);
        $this->recalcCargosOnSaved($shipment);
        $this->markOrderAsNonProblem($shipment);
        $this->markDeliveryAsProblem($shipment);
        $this->markOrderAsProblem($shipment);
        $this->markDeliveryAsNonProblem($shipment);
        $this->upsertDeliveryOrder($shipment);
        $this->add2Cargo($shipment);
        $this->add2CargoHistory($shipment);
        $this->sendCreatedNotification($shipment);
    }

    /**
     * Проверить, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
     */
    protected function checkAllProductsPacked(Shipment $shipment): bool
    {
        if (
            $shipment->status != $shipment->getOriginal('status') &&
            $shipment->status == ShipmentStatus::ASSEMBLED
        ) {
            /** @var ShipmentPackageService $shipmentPackageService */
            $shipmentPackageService = resolve(ShipmentPackageService::class);

            return $shipmentPackageService->checkAllShipmentProductsPacked($shipment);
        }

        return true;
    }

    /**
     * Пересчитать груз и доставку при сохранении отправления
     */
    protected function recalcCargoAndDeliveryOnSaved(Shipment $shipment): void
    {
        $needRecalc = false;
        foreach (['weight', 'width', 'height', 'length'] as $field) {
            if ($shipment->getOriginal($field) != $shipment[$field]) {
                $needRecalc = true;
                break;
            }
        }

        if ($needRecalc) {
            if ($shipment->cargo_id) {
                $shipment->cargo->recalc();
            }

            $shipment->delivery->recalc();
        }
    }

    /**
     * Пересчитать старый и новый грузы при сохранении отправления
     */
    protected function recalcCargosOnSaved(Shipment $shipment): void
    {
        $oldCargoId = $shipment->getOriginal('cargo_id');
        if ($oldCargoId != $shipment->cargo_id) {
            if ($oldCargoId) {
                /** @var Cargo $oldCargo */
                $oldCargo = Cargo::find($oldCargoId);
                if ($oldCargo) {
                    $oldCargo->recalc();
                }
            }
            if ($shipment->cargo_id) {
                /** @var Cargo $newCargo */
                $newCargo = Cargo::find($shipment->cargo_id);
                if ($newCargo) {
                    $newCargo->recalc();
                }
            }
        }
    }

    /**
     * Пометить доставку как проблемную в случае проблемного отправления
     */
    protected function markDeliveryAsProblem(Shipment $shipment): void
    {
        if (
            $shipment->is_problem != $shipment->getOriginal('is_problem') &&
            $shipment->is_problem
        ) {
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            $deliveryService->markAsProblem($shipment->delivery);
        }
    }

    /**
     * Пометить заказ как проблемный в случае проблемного отправления
     */
    protected function markOrderAsProblem(Shipment $shipment): void
    {
        if (
            $shipment->is_problem != $shipment->getOriginal('is_problem') &&
            $shipment->is_problem
        ) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->markAsProblem($shipment->delivery->order);
        }
    }

    /**
     * Пометить заказ как непроблемный, если все отправления непроблемные
     */
    protected function markOrderAsNonProblem(Shipment $shipment): void
    {
        if ($shipment->is_problem != $shipment->getOriginal('is_problem') && !$shipment->is_problem) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->markAsNonProblem($shipment->delivery->order);
        }
    }

    /**
     * Пометить доставку как непроблемную, если все отправления непроблемные
     */
    protected function markDeliveryAsNonProblem(Shipment $shipment): void
    {
        if ($shipment->is_problem != $shipment->getOriginal('is_problem') && !$shipment->is_problem) {
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            $deliveryService->markAsNonProblem($shipment->delivery);
        }
    }

    /**
     * Создать/обновить заказ на доставку
     * Создание заказа на доставку происходит когда все отправления доставки получают статус "Все товары отправления в наличии"
     * Обновление заказа на доставку происходит когда отправление доставки получает статус "Собрано"
     */
    protected function upsertDeliveryOrder(Shipment $shipment): void
    {
        if (
            ($shipment->is_canceled != $shipment->getOriginal('is_canceled')) ||
            ($shipment->status != $shipment->getOriginal('status') &&
                in_array($shipment->status, [ShipmentStatus::ASSEMBLING, ShipmentStatus::ASSEMBLED])
            )
        ) {
            try {
                $shipment->loadMissing('delivery.shipments');
                $delivery = $shipment->delivery;

                //в DPD создаем заказ только в статусе "собрано"
                if ($delivery->delivery_service == DeliveryServiceDto::SERVICE_DPD && $shipment->status == ShipmentStatus::ASSEMBLING) {
                    return;
                }

                /**
                 * todo костыль чтобы заказ на доставку не создавался два раза в DPD
                 * todo разобраться почему так
                 */
                if (!in_array($delivery->id, static::$createdDeliveryOrders)) {
                    /** @var DeliveryService $deliveryService */
                    $deliveryService = resolve(DeliveryService::class);
                    $deliveryService->saveDeliveryOrder($delivery);

                    if ($delivery->delivery_service == DeliveryServiceDto::SERVICE_DPD) {
                        static::$createdDeliveryOrders[] = $delivery->id;
                    }
                }

            } catch (DeliveryServiceInvalidConditions $e) {
                logger(['saveDeliveryOrder error' => $e->getMessage()]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Добавить отправление в груз
     */
    protected function add2Cargo(Shipment $shipment): void
    {
        try {
            /** @var ShipmentService $shipmentService */
            $shipmentService = resolve(ShipmentService::class);
            $shipmentService->addShipment2Cargo($shipment);
        } catch (DeliveryServiceInvalidConditions $e) {
            logger(['addShipment2Cargo error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Добавить информацию о добавлении/удалении отправления в/из груз/а
     */
    protected function add2CargoHistory(Shipment $shipment): void
    {
        if ($shipment->wasChanged('cargo_id')) {
            if ($shipment->getOriginal('cargo_id')) {
                $shipment->saveHistoryEvent(HistoryType::TYPE_DELETE_LINK, Cargo::find($shipment->getOriginal('cargo_id')));
            }

            if ($shipment->cargo_id) {
                $shipment->saveHistoryEvent(HistoryType::TYPE_DELETE_LINK, Cargo::find($shipment->cargo_id));
            }
        }
    }

    /**
     * Установить дату изменения статуса отправления
     */
    protected function setStatusAt(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status')) {
            $shipment->status_at = now();
        }
    }

    /**
     * Установить дату изменения статуса оплаты отправления
     */
    protected function setPaymentStatusAt(Shipment $shipment): void
    {
        if ($shipment->payment_status != $shipment->getOriginal('payment_status')) {
            $shipment->payment_status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемного отправления
     */
    protected function setProblemAt(Shipment $shipment): void
    {
        if ($shipment->is_problem != $shipment->getOriginal('is_problem')) {
            $shipment->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены отправления
     */
    protected function setCanceledAt(Shipment $shipment): void
    {
        if ($shipment->is_canceled != $shipment->getOriginal('is_canceled')) {
            $shipment->is_canceled_at = now();
        }
    }

    /**
     * Установить фактическую дату и время, когда отправление собрано (получило статус "Готово к отгрузке")
     */
    protected function setFsd(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') && $shipment->status == ShipmentStatus::ASSEMBLED) {
            $shipment->fsd = now();
        }
    }

    /**
     * Переводим в статус "Ожидает проверки АОЗ" из статуса "Оформлено",
     * если статус доставки "Ожидает проверки АОЗ"
     */
    protected function setAwaitingCheckStatus(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::CREATED && $shipment->delivery->status == DeliveryStatus::AWAITING_CHECK) {
            $shipment->status = ShipmentStatus::AWAITING_CHECK;
        }
    }

    /**
     * Переводим в статус "Ожидает подтверждения Мерчантом" из статуса "Оформлено",
     * если статус доставки "Ожидает подтверждения Мерчантом"
     */
    protected function setAwaitingConfirmationStatus(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::CREATED && $shipment->delivery->status == DeliveryStatus::AWAITING_CONFIRMATION) {
            $shipment->status = ShipmentStatus::AWAITING_CONFIRMATION;
        }
    }

    protected function setCheckingStatus(Shipment $shipment): void
    {
        $merchantService = resolve(MerchantService::class);
        $merchant = $merchantService->merchant($shipment->merchant_id);

        if ($shipment->getOriginal('status') == ShipmentStatus::CREATED && $shipment->status == ShipmentStatus::AWAITING_CONFIRMATION && $merchant->is_require_approval) {
            $shipment->status = ShipmentStatus::CHECKING;
        }
    }

    /**
     * Автоматическая установка статуса для доставки, если все её отправления получили нужный статус
     */
    protected function setStatusToDelivery(Shipment $shipment): void
    {
        if (isset(self::STATUS_TO_DELIVERY[$shipment->status]) && $shipment->status != $shipment->getOriginal('status')) {
            $delivery = $shipment->delivery;
            if ($delivery->status == self::STATUS_TO_DELIVERY[$shipment->status]) {
                return;
            }

            $allShipmentsHasStatus = true;
            foreach ($delivery->shipments as $deliveryShipment) {
                if ($deliveryShipment->id === $shipment->id) {
                    continue;
                }
                if ($deliveryShipment->is_canceled) {
                    continue;
                }

                if ($deliveryShipment->status < $shipment->status) {
                    $allShipmentsHasStatus = false;
                    break;
                }
            }

            if ($allShipmentsHasStatus) {
                $delivery->status = self::STATUS_TO_DELIVERY[$shipment->status];
                $delivery->save();
            }
        }
    }

    /**
     * Автоматическая установка флага отмены для доставки, если все её отправления отменены
     * @throws Exception
     */
    protected function setIsCanceledToDelivery(Shipment $shipment): void
    {
        if ($shipment->wasChanged('is_canceled') && $shipment->is_canceled) {
            $delivery = $shipment->delivery;
            if ($delivery->is_canceled) {
                return;
            }

            $allShipmentsIsCanceled = true;
            foreach ($delivery->shipments as $deliveryShipment) {
                if (!$deliveryShipment->is_canceled) {
                    $allShipmentsIsCanceled = false;
                    break;
                }
            }

            if ($allShipmentsIsCanceled) {
                /** @var DeliveryService $deliveryService */
                $deliveryService = resolve(DeliveryService::class);
                $deliveryService->cancelDelivery($delivery, $shipment->return_reason_id);
            }
        }
    }

    /**
     * Установить флаг отмены всем элементам отправления
     * @throws Exception
     */
    protected function setIsCanceledToBasketItems(Shipment $shipment): void
    {
        if ($shipment->is_canceled && $shipment->is_canceled != $shipment->getOriginal('is_canceled')) {
            $shipment->loadMissing('basketItems');
            foreach ($shipment->basketItems as $basketItem) {
                $basketItem->is_canceled = true;
                $basketItem->save();
            }
        }
    }

    /**
     * Установка заказу флага частичной отмены
     */
    protected function setOrderIsPartiallyCancelled(Shipment $shipment): void
    {
        if ($shipment->wasChanged('is_canceled') && $shipment->is_canceled) {
            $order = $shipment->delivery->order;
            if (!$order->is_canceled) {
                $order->is_partially_cancelled = true;
                $order->save();
            }
        }
    }

    /**
     * Автоматическая установка статуса "Принят Логистическим Оператором" для груза,
     * если все его отправления получили статус "Принято Логистическим Оператором"
     */
    protected function setTakenStatusToCargo(Shipment $shipment): void
    {
        if (
            $shipment->status >= ShipmentStatus::ON_POINT_IN && $shipment->status <= ShipmentStatus::DONE
            && $shipment->wasChanged('status')
        ) {
            $cargo = $shipment->cargo;
            if (empty($cargo) || $cargo->status == CargoStatus::TAKEN) {
                return;
            }

            $allShipmentsHasStatus = true;
            foreach ($cargo->shipments as $cargoShipment) {
                if ($cargoShipment->status < ShipmentStatus::ON_POINT_IN) {
                    $allShipmentsHasStatus = false;
                    break;
                }
            }

            if ($allShipmentsHasStatus) {
                $cargo->status = CargoStatus::TAKEN;
                $cargo->save();
            }
        }
    }

    /**
     * Переводим доставку в статус "Предзаказ: ожидаем поступления товара",
     * если статус отправления "Предзаказ: ожидаем поступления товара"
     */
    protected function setPreOrderStatusToDelivery(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::PRE_ORDER) {
            $delivery = $shipment->delivery;
            $delivery->status = DeliveryStatus::PRE_ORDER;
            $delivery->save();
        }
    }

    public function sendCreatedNotification(Shipment $shipment): void
    {
        if (!in_array($shipment->status, self::ELIGIBLE_STATUS)) {
            return;
        }

        if ($shipment->status == $shipment->getOriginal('status')) {
            return;
        }

        // if(in_array($shipment->getOriginal('status'), static::ELIGIBLE_STATUS)) {
        //     return true;
        // }

        /** @var ShipmentService $shipmentService */
        $shipmentService = resolve(ShipmentService::class);
        $shipmentService->sendShipmentNotification($shipment);
    }

    protected function sendStatusNotification(Shipment $shipment)
    {
        try {
            $isNeedSendCanceledNotification = $this->isNeedSendCanceledNotification($shipment);
            $isNeedSendProblemNotification = $shipment->wasChanged('is_problem') && $shipment->is_problem;

            if (!$isNeedSendCanceledNotification && !$isNeedSendProblemNotification) {
                return;
            }

            $serviceNotificationService = app(ServiceNotificationService::class);
            $operatorService = app(OperatorService::class);
            $userService = app(UserService::class);

            $operators = $operatorService->operators((new RestQuery())->setFilter(
                'merchant_id',
                '=',
                $shipment->merchant_id
            ));

            foreach ($operators as $i => $operator) {
                /** @var UserDto $user */
                $user = $userService->users(
                    $userService->newQuery()
                        ->setFilter('id', $operator->user_id)
                )->first();

                if ($i === 0 && $isNeedSendCanceledNotification) {
                    $serviceNotificationService->send(
                        $user->id,
                        'klientstatus_zakaza_otmenen',
                        $this->cancelledNotificationAttributes($shipment, $user),
                    );
                }

                switch ($operator->communication_method) {
                    case OperatorCommunicationMethod::METHOD_PHONE:
                        $receiver = $user->phone;
                        $channel = 'sms';
                        break;
                    case OperatorCommunicationMethod::METHOD_EMAIL:
                        $receiver = $user->email;
                        $channel = 'email';
                        break;
                    default:
                        continue 2;
                }

                if ($isNeedSendCanceledNotification) {
                    $serviceNotificationService->sendDirect(
                        'klientstatus_zakaza_otmenen',
                        $receiver,
                        $channel,
                        $this->cancelledNotificationAttributes($shipment, $user),
                    );
                }

                if ($isNeedSendProblemNotification) {
                    $serviceNotificationService->sendDirect(
                        'klientstatus_zakaza_problemnyy',
                        $receiver,
                        $channel,
                        [
                            'QUANTITY_ORDERS' => 1,
                            'LINK_ORDERS' => sprintf('%s/shipment/%d', config('mas.masHost'), $shipment->id),
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function isNeedSendCanceledNotification(Shipment $shipment): bool
    {
        return $shipment->wasChanged('is_canceled')
            && $shipment->is_canceled
            && $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION;
    }

    private function cancelledNotificationAttributes(Shipment $shipment, UserDto $user): array
    {
        return [
            'QUANTITY_ORDERS' => 1,
            'ORDER_NUMBER' => $shipment->delivery->order->number ?? '',
            'CUSTOMER_NAME' => $user->first_name ?? '',
            'LINK_ORDERS' => sprintf('%s/shipment/list/%d', config('mas.masHost'), $shipment->id),
            'PRICE_GOODS' => (int) $shipment->items->first()->basketItem->price,
        ];
    }
}
