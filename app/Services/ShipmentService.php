<?php

namespace App\Services;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Services\Dto\In\OrderReturn\OrderReturnDtoBuilder;
use Exception;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Logistics\Dto\Order\CdekDeliveryOrderReceiptDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderBarcodesDto;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MerchantManagement\Dto\OperatorCommunicationMethod;
use MerchantManagement\Dto\OperatorDto;
use MerchantManagement\Services\OperatorService\OperatorService;
use Throwable;

/**
 * Класс-бизнес логики по работе с сущностями отправлениями
 * Class ShipmentService
 * @package App\Services
 */
class ShipmentService
{
    protected DeliveryService $deliveryService;
    protected CargoService $cargoService;
    protected ServiceNotificationService $notificationService;

    public function __construct()
    {
        $this->deliveryService = resolve(DeliveryService::class);
        $this->cargoService = resolve(CargoService::class);
        $this->notificationService = app(ServiceNotificationService::class);
    }

    /**
     * Получить объект отправления по его id
     *
     * @throws ModelNotFoundException
     */
    public function getShipment(int $shipmentId): Shipment
    {
        return Shipment::findOrFail($shipmentId);
    }

    /**
     * Добавить отправление в груз
     * @throws Exception
     */
    public function addShipment2Cargo(Shipment $shipment): void
    {
        if ($shipment->isInvalid()) {
            throw new DeliveryServiceInvalidConditions('Отправление отменено или проблемное');
        }
        if ($shipment->status != ShipmentStatus::AWAITING_CONFIRMATION) {
            throw new DeliveryServiceInvalidConditions(
                'Отправление не в статусе ожидания подтверждения мерчантом'
            );
        }
        if ($shipment->cargo_id) {
            throw new DeliveryServiceInvalidConditions('Отправление уже добавлено в груз');
        }

        $deliveryServiceId = $this->deliveryService->getZeroMileShipmentDeliveryServiceId($shipment);

        $cargoQuery = Cargo::query()
            ->select('id')
            ->where('merchant_id', $shipment->merchant_id)
            ->where('store_id', $shipment->store_id)
            ->where('delivery_service', $deliveryServiceId)
            ->where('status', CargoStatus::CREATED)
            ->where('is_canceled', false)
            ->orderBy('created_at', 'desc');
        if ($shipment->getOriginal('cargo_id')) {
            $cargoQuery->where('id', '!=', $shipment->getOriginal('cargo_id'));
        }
        $cargo = $cargoQuery->first();
        if (is_null($cargo)) {
            $cargo = $this->cargoService->createCargo($shipment, $deliveryServiceId);
        }

        $shipment->cargo_id = $cargo->id;
        $shipment->save();
    }

    /**
     * Получить файл со штрихкодами коробок для заказа на доставку
     */
    public function getShipmentBarcodes(Shipment $shipment): ?DeliveryOrderBarcodesDto
    {
        $delivery = $shipment->delivery;

        if (!$delivery->xml_id) {
            return null;
        }
        if ($shipment->status < ShipmentStatus::ASSEMBLED) {
            return null;
        }

        try {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            return $deliveryOrderService->barcodesOrder(
                $delivery->delivery_service,
                $delivery->xml_id,
                array_filter($shipment->packages->pluck('xml_id')->toArray())
            );
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Получить квитанцию cdek для заказа на доставку
     * @return DeliveryOrderBarcodesDto|null
     */
    public function getShipmentCdekReceipt(Shipment $shipment): ?CdekDeliveryOrderReceiptDto
    {
        $delivery = $shipment->delivery;

        if (!$delivery->xml_id) {
            return null;
        }
        if ($shipment->status < ShipmentStatus::ASSEMBLED) {
            return null;
        }

        try {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            return $deliveryOrderService->cdekReceiptOrder($delivery->delivery_service, $delivery->xml_id);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Пометить отправление как проблемное
     */
    public function markAsProblemShipment(Shipment $shipment, string $comment = ''): bool
    {
        $shipment->is_problem = true;
        $shipment->assembly_problem_comment = $comment;

        return $shipment->save();
    }

    /**
     * Пометить отправление как непроблемное
     */
    public function markAsNonProblemShipment(Shipment $shipment): bool
    {
        $shipment->is_problem = false;

        return $shipment->save();
    }

    /**
     * Отменить отправление
     * @throws Exception
     */
    public function cancelShipment(Shipment $shipment, ?int $orderReturnReasonId = null): bool
    {
        if ($shipment->status >= ShipmentStatus::DONE) {
            throw new DeliveryServiceInvalidConditions(
                'Отправление, начиная со статуса "Доставлено получателю", нельзя отменить'
            );
        }

        $shipment->return_reason_id ??= $orderReturnReasonId;

        $shipment->is_canceled = true;
        $shipment->cargo_id = null;

        if (!$shipment->save()) {
            return false;
        }

        $orderReturnDto = (new OrderReturnDtoBuilder())->buildFromShipment($shipment);

        if ($orderReturnDto) {
            /** @var OrderReturnService $orderReturnService */
            $orderReturnService = resolve(OrderReturnService::class);
            rescue(fn() => $orderReturnService->create($orderReturnDto));
        }

        if ($shipment->status > ShipmentStatus::CREATED) {
            $attributes = [
                'SHIPMENT_NUMBER' => $shipment->number,
                'LINK_ORDER' => sprintf('%s/orders/%d', config('app.admin_host'), $shipment->delivery->order_id),
            ];

            /** @var ServiceNotificationService $notificationService */
            $notificationService = resolve(ServiceNotificationService::class);
            $notificationService->sendByRole(RoleDto::ROLE_LOGISTIC, 'logistotpravlenie_otmeneno', $attributes);
        }

        return true;
    }

    /**
     * Отправить уведомление мерчанту о созданном отправлении
     */
    public function sendShipmentNotification(Shipment $shipment)
    {
        try {
            /** @var OperatorService $operatorService */
            $operatorService = app(OperatorService::class);
            /** @var UserService $userService */
            $userService = app(UserService::class);

            $operators = $operatorService->operators(
                (new RestQuery())->setFilter('merchant_id', '=', $shipment->merchant_id)
            );

            $users = $userService->users(
                $userService->newQuery()->setFilter('id', $operators->pluck('user_id')->all())
            )->keyBy('id');

            /** @var OperatorDto $operator */
            foreach ($operators as $i => $operator) {
                $user = $users->get($operator->user_id);
                $attributes = $this->generateNotificationAttributes($shipment, $user);

                if ($user) {
                    if ($i === 0) { // TODO: добавить проверку, что оператор является админом
                        $this->notificationService->send(
                            $operator->user_id,
                            'klientoformlen_novyy_zakaz',
                            $this->generateNotificationAttributes($shipment, $user)
                        );
                        continue;
                    }

                    switch ($operator->communication_method) {
                        case OperatorCommunicationMethod::METHOD_PHONE:
                            $this->notificationService->sendDirect('klientoformlen_novyy_zakaz', $user->phone, 'sms', $attributes);
                            break;
                        case OperatorCommunicationMethod::METHOD_EMAIL:
                            $this->notificationService->sendDirect('klientoformlen_novyy_zakaz', $user->email, 'email', $attributes);
                            break;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Сформировать параметры для уведомления
     */
    protected function generateNotificationAttributes(Shipment $shipment, ?UserDto $user): array
    {
        return [
            'QUANTITY_ORDERS' => 1,
            'LINK_ORDERS' => sprintf('%s/shipment/list/%d', config('mas.masHost'), $shipment->id),
            'CUSTOMER_NAME' => $user ? $user->first_name : '',
            'SUM_ORDERS' => (int) $shipment->cost,
            'GOODS_NAME' => $shipment->items->first()->basketItem->name,
            'QUANTITY_GOODS' => (int) $shipment->items->first()->basketItem->qty,
            'PRISE_GOODS' => (int) $shipment->items->first()->basketItem->price,
            'ALL_QUANTITY_GOODS' => (int) $shipment->items()->with('basketItem')->get()->sum('basketItem.qty'),
            'ALL_PRISE_GOODS' => (int) $shipment->cost,
        ];
    }
}
