<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use Exception;
use Greensight\CommonMsa\Services\IbtService\IbtService;
use Greensight\Logistics\Dto\Lists\ShipmentMethod;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderCostDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderInputDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderItemDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderPlaceDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\RecipientDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\SenderDto;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;

/**
 * Class ShipmentObserver
 * @package App\Observers\Delivery
 */
class ShipmentObserver
{
    /**
     * Handle the shipment "created" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function created(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the shipment "updating" event.
     * @param  Shipment $shipment
     * @return bool
     */
    public function updating(Shipment $shipment): bool
    {
        if (!$this->checkAllProductsPacked($shipment)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle the shipment "updated" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function updated(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the shipment "deleting" event.
     * @param  Shipment $shipment
     * @throws Exception
     */
    public function deleting(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, [$shipment->delivery->order, $shipment], $shipment);
    
        foreach ($shipment->packages as $package) {
            $package->delete();
        }
    }
    
    /**
     * Handle the shipment "deleted" event.
     * @param  Shipment $shipment
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
     * Handle the shipment "saving" event.
     * @param  Shipment  $shipment
     */
    public function saving(Shipment $shipment)
    {
        $this->add2Cargo($shipment);
    }
    
    /**
     * Handle the shipment "saved" event.
     * @param  Shipment $shipment
     * @throws Exception
     */
    public function saved(Shipment $shipment)
    {
        $this->recalcCargoAndDeliveryOnSaved($shipment);
        $this->recalcCargosOnSaved($shipment);
        $this->markOrderAsProblem($shipment);
        $this->markOrderAsNonProblem($shipment);
        $this->upsertDeliveryOrder($shipment);
        $this->add2CargoHistory($shipment);
    }
    
    /**
     * Проверить, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
     * @param Shipment $shipment
     * @return bool
     */
    protected function checkAllProductsPacked(Shipment $shipment): bool
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->status == ShipmentStatus::STATUS_ASSEMBLED
        ) {
            $shipment->load('items.basketItem', 'packages.items');
        
            $shipmentItems = [];
            foreach ($shipment->items as $shipmentItem) {
                $shipmentItems[$shipmentItem->basket_item_id] = $shipmentItem->basketItem->qty;
            }
        
            foreach ($shipment->packages as $package) {
                foreach ($package->items as $packageItem) {
                    $shipmentItems[$packageItem->basket_item_id] -= $packageItem->qty;
                }
            }
        
            return empty(array_filter($shipmentItems));
        }
        
        return true;
    }
    
    /**
     * Пересчитать груз и доставку при сохранении отправления
     * @param Shipment $shipment
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
     * @param Shipment $shipment
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
     * Пометить заказ как проблемный в случае проблемного отправления
     * @param Shipment $shipment
     */
    protected function markOrderAsProblem(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            in_array($shipment->status, [ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT])) {
            $order = $shipment->delivery->order;
            $order->is_problem = true;
            $order->save();
        }
    }
    
    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param Shipment $shipment
     */
    protected function markOrderAsNonProblem(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->getOriginal('status') == ShipmentStatus::STATUS_ASSEMBLING_PROBLEM) {
            $order = $shipment->delivery->order;
            $isAllShipmentsOk = true;
            foreach ($order->deliveries as $delivery) {
                foreach ($delivery->shipments as $shipment) {
                    if (in_array($shipment->status, [
                        ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT
                    ])) {
                        $isAllShipmentsOk = false;
                        break 2;
                    }
                }
            }
        
            $order->is_problem = !$isAllShipmentsOk;
            $order->save();
        }
    }
    
    /**
     * Создать/обновить заказ на доставку
     * Создание заказа на доставку происходит когда все отправления доставки получают статус "Все товары отправления в наличии"
     * Обновление заказа на доставку происходит когда отправление доставки получает статус "Собрано"
     * @param Shipment $shipment
     */
    protected function upsertDeliveryOrder(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            in_array($shipment->status, [ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE, ShipmentStatus::STATUS_ASSEMBLED])
        ) {
            $shipment->load('delivery.shipments');
            $delivery = $shipment->delivery;
            
            foreach ($delivery->shipments as $deliveryShipment) {
                if (!in_array($deliveryShipment->status, [
                    ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE,
                    ShipmentStatus::STATUS_ASSEMBLED,
                ])) {
                    return;
                }
            }
            
            try {
                $deliveryOrderInputDto = $this->formDeliveryOrder($delivery);
                /** @var DeliveryOrderService $deliveryOrderService */
                $deliveryOrderService = resolve(DeliveryOrderService::class);
                if (!$delivery->xml_id) {
                    $deliveryOrderOutputDto = $deliveryOrderService->createOrder($delivery->delivery_service, $deliveryOrderInputDto);
                    if ($deliveryOrderOutputDto->xml_id) {
                        $delivery->xml_id = $deliveryOrderOutputDto->xml_id;
                        $delivery->save();
                    }
                } else {
                    $deliveryOrderService->updateOrder(
                        $delivery->delivery_service,
                        $delivery->xml_id,
                        $deliveryOrderInputDto
                    );
                }
            } catch (Exception $e) {
                //todo Сообщать об ошибке выгрузки заказа в СД
            }
        }
    }
    
    /**
     * Сформировать заказ на доставку
     * @param Delivery $delivery
     * @return DeliveryOrderInputDto
     */
    protected function formDeliveryOrder(Delivery $delivery): DeliveryOrderInputDto
    {
        $delivery->load(['order', 'shipments.packages.items.basketItem']);
        $deliveryOrderInputDto = new DeliveryOrderInputDto();
        
        //Информация о заказе
        $deliveryOrderDto = new DeliveryOrderDto();
        $deliveryOrderInputDto->order = $deliveryOrderDto;
        $deliveryOrderDto->number = $delivery->number;
        $deliveryOrderDto->height = $delivery->height;
        $deliveryOrderDto->length = $delivery->length;
        $deliveryOrderDto->width = $delivery->width;
        $deliveryOrderDto->shipment_method = ShipmentMethod::METHOD_DS_COURIER;
        $deliveryOrderDto->delivery_method = $delivery->delivery_method;
        $deliveryOrderDto->tariff_id = $delivery->tariff_id;
        $deliveryOrderDto->delivery_date = $delivery->delivery_at;
        $deliveryOrderDto->point_out_id = $delivery->point_id;
        
        //Информация о стоимосте заказа
        $deliveryOrderCostDto = new DeliveryOrderCostDto();
        $deliveryOrderInputDto->cost = $deliveryOrderCostDto;
        $deliveryOrderCostDto->delivery_cost = $delivery->cost;
        
        //Информация об отправителе заказа
        /** @var IbtService $ibtService */
        $ibtService = resolve(IbtService::class);
        $centralStoreAddress = $ibtService->getCentralStoreAddress();
        $senderDto = new SenderDto($centralStoreAddress);
        $deliveryOrderInputDto->sender = $senderDto;
        $senderDto->address_string = implode(', ', array_filter([
            $senderDto->post_index,
            $senderDto->region != $senderDto->city ? $senderDto->region : '',
            $senderDto->area,
            $senderDto->city,
            $senderDto->street,
            $senderDto->house,
            $senderDto->block,
            $senderDto->flat,
        ]));
        $senderDto->company_name = $ibtService->getCompanyName();
        $senderDto->contact_name = $ibtService->getCentralStoreContactName();
        $senderDto->email = $ibtService->getCentralStoreEmail();
        $senderDto->phone = $ibtService->getCentralStorePhone();
    
        //Информация об получателе заказа
        $recipientDto = new RecipientDto($delivery->delivery_address);
        $deliveryOrderInputDto->recipient = $recipientDto;
        $recipientDto->address_string = implode(', ', array_filter([
            $recipientDto->post_index,
            $recipientDto->region != $recipientDto->city ? $recipientDto->region : '',
            $recipientDto->area,
            $recipientDto->city,
            $recipientDto->street,
            $recipientDto->house,
            $recipientDto->block,
            $recipientDto->flat,
        ]));
        $recipientDto->contact_name = $delivery->receiver_name;
        $recipientDto->email = $delivery->receiver_email;
        $recipientDto->phone = $delivery->receiver_phone;
        
        //Информация о местах (коробках) заказа
        $places = collect();
        $deliveryOrderInputDto->places = $places;
        $packageNumber = 1;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->packages && $shipment->packages->isNotEmpty()) {
                foreach ($shipment->packages as $package) {
                    $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                    $places->push($deliveryOrderPlaceDto);
                    $deliveryOrderPlaceDto->number = $packageNumber++;
                    $deliveryOrderPlaceDto->code = $package->id;
                    $deliveryOrderPlaceDto->width = (int)ceil($package->width);
                    $deliveryOrderPlaceDto->height = (int)ceil($package->height);
                    $deliveryOrderPlaceDto->length = (int)ceil($package->length);
                    $deliveryOrderPlaceDto->weight = (int)ceil($package->weight);
    
                    $items = collect();
                    $deliveryOrderPlaceDto->items = $items;
                    foreach ($package->items as $item) {
                        $basketItem = $item->basketItem;
                        $deliveryOrderItemDto = new DeliveryOrderItemDto();
                        $items->push($deliveryOrderItemDto);
                        $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                        $deliveryOrderItemDto->name = $basketItem->name;
                        $deliveryOrderItemDto->quantity = (float)$basketItem->qty;
                        $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int)ceil($basketItem->product['height']) : 0;
                        $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int)ceil($basketItem->product['width']) : 0;
                        $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int)ceil($basketItem->product['length']) : 0;
                        $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int)ceil($basketItem->product['weight']) : 0;
                        $deliveryOrderItemDto->cost = round($basketItem->qty > 0 ? $basketItem->price / $basketItem->qty : 0, 2);
                    }
                }
            } else {
                $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                $places->push($deliveryOrderPlaceDto);
                $deliveryOrderPlaceDto->number = $packageNumber++;
                $deliveryOrderPlaceDto->code = $shipment->number;
                $deliveryOrderPlaceDto->width = (int)ceil($shipment->width);
                $deliveryOrderPlaceDto->height = (int)ceil($shipment->height);
                $deliveryOrderPlaceDto->length = (int)ceil($shipment->length);
                $deliveryOrderPlaceDto->weight = (int)ceil($shipment->weight);
    
                $items = collect();
                $deliveryOrderPlaceDto->items = $items;
                foreach ($shipment->items as $item) {
                    $basketItem = $item->basketItem;
                    $deliveryOrderItemDto = new DeliveryOrderItemDto();
                    $items->push($deliveryOrderItemDto);
                    $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                    $deliveryOrderItemDto->name = $basketItem->name;
                    $deliveryOrderItemDto->quantity = (float)$basketItem->qty;
                    $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int)ceil($basketItem->product['height']) : 0;
                    $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int)ceil($basketItem->product['width']) : 0;
                    $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int)ceil($basketItem->product['length']) : 0;
                    $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int)ceil($basketItem->product['weight']) : 0;
                    $deliveryOrderItemDto->cost = round($basketItem->qty > 0 ? $basketItem->price / $basketItem->qty : 0, 2);
                }
            }
        }
        
        return $deliveryOrderInputDto;
    }
    
    /**
     * Добавить отправление в груз
     * @param Shipment $shipment
     */
    protected function add2Cargo(Shipment $shipment): void
    {
        if (!$shipment->cargo_id &&
            $shipment->status == ShipmentStatus::STATUS_ASSEMBLED
        ) {
            /**
             * Если у отправления указана служба доставки на нулевой миле, то используем её для груза.
             * Иначе для груза используем службы доставки для доставки, к которой принадлежит отправление
             */
            $deliveryService = $shipment->delivery_service_zero_mile;
            if (!$deliveryService) {
                $shipment->load('delivery');
                $deliveryService = $shipment->delivery->delivery_service;
            }
            
            $cargoQuery = Cargo::query()
                ->select('id')
                ->where('merchant_id', $shipment->merchant_id)
                ->where('store_id', $shipment->store_id)
                ->where('delivery_service', $deliveryService)
                ->where('status', CargoStatus::STATUS_CREATED)
                ->orderBy('created_at', 'desc');
            if ($shipment->getOriginal('cargo_id')) {
                $cargoQuery->where('id', '!=', $shipment->getOriginal('cargo_id'));
            }
            $cargo = $cargoQuery->first();
            if (is_null($cargo)) {
                $cargo = new Cargo();
                $cargo->merchant_id = $shipment->merchant_id;
                $cargo->store_id = $shipment->store_id;
                $cargo->delivery_service = $deliveryService;
                $cargo->status = CargoStatus::STATUS_CREATED;
                $cargo->width = 0;
                $cargo->height = 0;
                $cargo->length = 0;
                $cargo->weight = 0;
                $cargo->save();
            }
            
            $shipment->cargo_id = $cargo->id;
        }
    }
    
    /**
     * Добавить информацию о добавлении/удалении отправления в/из груз/а
     * @param  Shipment  $shipment
     */
    protected function add2CargoHistory(Shipment $shipment): void
    {
        if ($shipment->cargo_id != $shipment->getOriginal('cargo_id')) {
            if ($shipment->getOriginal('cargo_id')) {
                History::saveEvent(HistoryType::TYPE_DELETE_LINK, Cargo::find($shipment->getOriginal('cargo_id')), $shipment);
            }
            
            if ($shipment->cargo_id) {
                History::saveEvent(HistoryType::TYPE_CREATE_LINK, Cargo::find($shipment->cargo_id), $shipment);
            }
        }
    }
}
