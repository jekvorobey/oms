<?php

namespace App\Services;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Exception;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Класс-бизнес логики по работе с сущностями грузов
 * Class CargoService
 * @package App\Services
 */
class CargoService
{
    protected DeliveryService $deliveryService;
    protected ShipmentService $shipmentService;
    protected ServiceNotificationService $notificationService;

    public function __construct()
    {
        $this->deliveryService = resolve(DeliveryService::class);
        $this->shipmentService = resolve(ShipmentService::class);
        $this->notificationService = app(ServiceNotificationService::class);
    }

    /**
     * Получить объект груза по его id
     *
     * @throws ModelNotFoundException
     */
    public function getCargo(int $cargoId): Cargo
    {
        return Cargo::findOrFail($cargoId);
    }

    public function createCargo(Shipment $shipment, int $deliveryService): Cargo
    {
        $cargo = new Cargo();
        $cargo->merchant_id = $shipment->merchant_id;
        $cargo->store_id = $shipment->store_id;
        $cargo->delivery_service = $deliveryService;
        $cargo->status = CargoStatus::CREATED;
        $cargo->width = 0;
        $cargo->height = 0;
        $cargo->length = 0;
        $cargo->weight = 0;
        $cargo->save();

        return $cargo;
    }

    /**
     * Отменить груз (все отправления груза отвязываются от него!)
     * @throws Exception
     */
    public function cancelCargo(Cargo $cargo): bool
    {
        if ($cargo->status >= CargoStatus::SHIPPED) {
            throw new DeliveryServiceInvalidConditions(
                'Груз, начиная со статуса "Передан Логистическому Оператору", нельзя отменить'
            );
        }

        $result = DB::transaction(function () use ($cargo) {
            $cargo->is_canceled = true;
            $cargo->save();

            if ($cargo->shipments->isNotEmpty()) {
                foreach ($cargo->shipments as $shipment) {
                    $shipment->cargo_id = null;
                    $shipment->save();
                }
            }

            return true;
        });

        if ($result) {
            $this->deliveryService->cancelCourierCall($cargo);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Проверить статус отправлений груза в день его забора
     * @throws Exception
     */
    public function checkShipmentsStatusInCargo(Cargo $cargo): void
    {
        if (!$cargo->intake_date) {
            throw new Exception('Дата забора груза не установлена');
        }
        if (!$cargo->intake_date->isToday()) {
            return;
        }

        $cargo->loadMissing('shipments');
        $isNeedToCancelCourierCall = true;
        foreach ($cargo->shipments as $shipment) {
            if ($shipment->isInvalid()) {
                $shipment->cargo_id = null;
                $shipment->save();
            } else {
                $isNeedToCancelCourierCall = false;
            }

            if ($shipment->status === ShipmentStatus::AWAITING_CONFIRMATION) {
                //Отправка повторного уведомления мерчанту
                $this->shipmentService->sendShipmentNotification($shipment);

                //Отправка повторного уведомления логистам
                $attributes = [
                    'SHIPMENT_NUMBER' => $shipment->number,
                    'LINK_ORDER' => sprintf('%s/orders/%d', config('app.admin_host'), $shipment->delivery->order_id),
                    'LINK_CARGO' => sprintf('%s/orders/cargos/%d', config('app.admin_host'), $cargo->id),
                ];
                $this->notificationService->sendByRole(RoleDto::ROLE_LOGISTIC, 'logist_otpravlenie_need_processing', $attributes);
            }
        }

        if ($isNeedToCancelCourierCall) {
            $this->deliveryService->cancelCourierCall($cargo);
        }
    }
}
