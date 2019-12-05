<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use Greensight\CommonMsa\Services\IbtService\IbtService;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderCostDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderInputDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderItemDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderPlaceDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderRecipientDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderSenderDto;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Illuminate\Support\Collection;

/**
 * Class ShipmentObserver
 * @package App\Observers\Delivery
 */
class ShipmentObserver
{
    /**
     * Handle the order "created" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function created(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the order "updating" event.
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
     * Handle the order "updated" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function updated(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function deleting(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, [$shipment->delivery->order, $shipment], $shipment);
    
        foreach ($shipment->packages as $package) {
            $package->delete();
        }
    }
    
    /**
     * Handle the order "deleted" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function deleted(Shipment $shipment)
    {
        $this->recalcCargoOnDelete($shipment);
    }
    
    /**
     * Handle the order "saved" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function saved(Shipment $shipment)
    {
        $this->recalcCargoOnSaved($shipment);
        $this->markOrderAsProblem($shipment);
        $this->markOrderAsNonProblem($shipment);
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
     * Пересчитать груз при удалении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargoOnDelete(Shipment $shipment): void
    {
        if ($shipment->cargo_id) {
            /** @var Cargo $newCargo */
            $newCargo = Cargo::find($shipment->cargo_id);
            if ($newCargo) {
                $newCargo->recalc();
            }
        }
    }
    
    /**
     * Пересчитать груз при сохранении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargoOnSaved(Shipment $shipment): void
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
            $shipment->getOriginal('status') == ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE) {
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
            
            $deliveryOrderInputDto = $this->formDeliveryOrder($delivery);
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            if (!$delivery->xml_id) {
                $deliveryOrderService->createOrder($delivery->delivery_service, $deliveryOrderInputDto);
            } else {
                $deliveryOrderService->updateOrder(
                    $delivery->delivery_service,
                    $delivery->xml_id,
                    $deliveryOrderInputDto
                );
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
        $deliveryOrderDto->pickup_type = 1; //todo указано жестко, т.к. не понятно кто будет доставлять заказ от мерчанта до РЦ на нулевой миле
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
        $deliveryOrderSenderDto = new DeliveryOrderSenderDto($centralStoreAddress);
        $deliveryOrderInputDto->sender = $deliveryOrderSenderDto;
        $deliveryOrderSenderDto->address_string = implode(', ', [
            $deliveryOrderSenderDto->post_index,
            $deliveryOrderSenderDto->region != $deliveryOrderSenderDto->city ? $deliveryOrderSenderDto->region : '',
            $deliveryOrderSenderDto->area,
            $deliveryOrderSenderDto->city,
            $deliveryOrderSenderDto->street,
            $deliveryOrderSenderDto->house,
            $deliveryOrderSenderDto->block,
            $deliveryOrderSenderDto->flat,
        ]);
        $deliveryOrderSenderDto->company_name = $ibtService->getCompanyName();
        $deliveryOrderSenderDto->contact_name = $ibtService->getCentralStoreContactName();
        $deliveryOrderSenderDto->email = $ibtService->getCentralStoreEmail();
        $deliveryOrderSenderDto->phone = $ibtService->getCentralStorePhone();
    
        //Информация об получателе заказа
        $deliveryOrderRecipientDto = new DeliveryOrderRecipientDto($delivery->order->delivery_address);
        $deliveryOrderInputDto->recipient = $deliveryOrderRecipientDto;
        $deliveryOrderRecipientDto->address_string = implode(', ', [
            $deliveryOrderRecipientDto->post_index,
            $deliveryOrderRecipientDto->region != $deliveryOrderRecipientDto->city ? $deliveryOrderRecipientDto->region : '',
            $deliveryOrderRecipientDto->area,
            $deliveryOrderRecipientDto->city,
            $deliveryOrderRecipientDto->street,
            $deliveryOrderRecipientDto->house,
            $deliveryOrderRecipientDto->block,
            $deliveryOrderRecipientDto->flat,
        ]);
        $deliveryOrderRecipientDto->contact_name = $delivery->order->receiver_name;
        $deliveryOrderRecipientDto->email = $delivery->order->receiver_email;
        $deliveryOrderRecipientDto->phone = $delivery->order->receiver_phone;
        
        //Информация о местах (коробках) заказа
        $places = collect();
        $deliveryOrderInputDto->places = $places;
        $packageNumber = 1;
        foreach ($delivery->shipments as $shipment) {
            if (!is_null($shipment->packages)) {
                foreach ($shipment->packages as $package) {
                    $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                    $places->push($deliveryOrderPlaceDto);
                    $deliveryOrderPlaceDto->number = $packageNumber++;
                    $deliveryOrderPlaceDto->code = $package->id;
                    $deliveryOrderPlaceDto->width = $package->width;
                    $deliveryOrderPlaceDto->height = $package->height;
                    $deliveryOrderPlaceDto->length = $package->length;
                    $deliveryOrderPlaceDto->weight = $package->weight;
    
                    $items = collect();
                    $deliveryOrderPlaceDto->items = $items;
                    foreach ($package->items as $item) {
                        $basketItem = $item->basketItem;
                        $deliveryOrderItemDto = new DeliveryOrderItemDto();
                        $items->push($deliveryOrderItemDto);
                        $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                        $deliveryOrderItemDto->name = $basketItem->name;
                        $deliveryOrderItemDto->quantity = $basketItem->qty;
                        $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? $basketItem->product['height'] : 0;
                        $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? $basketItem->product['width'] : 0;
                        $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? $basketItem->product['length'] : 0;
                        $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? $basketItem->product['weight'] : 0;
                        $deliveryOrderItemDto->cost = $basketItem->qty > 0 ? $basketItem->price / $basketItem->qty : 0;
                    }
                }
            } else {
                $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                $places->push($deliveryOrderPlaceDto);
                $deliveryOrderPlaceDto->number = $packageNumber++;
                $deliveryOrderPlaceDto->code = $shipment->number;
                $deliveryOrderPlaceDto->width = 30;//todo Доделать примерный расчет замеров по размерам товаров
                $deliveryOrderPlaceDto->height = 30;//todo Доделать примерный расчет замеров по размерам товаров
                $deliveryOrderPlaceDto->length = 30;//todo Доделать примерный расчет замеров по размерам товаров
                $deliveryOrderPlaceDto->weight = 0;
    
                $items = collect();
                $deliveryOrderPlaceDto->items = $items;
                foreach ($shipment->items as $item) {
                    $basketItem = $item->basketItem;
                    $deliveryOrderItemDto = new DeliveryOrderItemDto();
                    $items->push($deliveryOrderItemDto);
                    $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                    $deliveryOrderItemDto->name = $basketItem->name;
                    $deliveryOrderItemDto->quantity = $basketItem->qty;
                    $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? $basketItem->product['height'] : 0;
                    $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? $basketItem->product['width'] : 0;
                    $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? $basketItem->product['length'] : 0;
                    $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? $basketItem->product['weight'] : 0;
                    $deliveryOrderPlaceDto->weight += $basketItem->qty * $deliveryOrderItemDto->weight;
                    $deliveryOrderItemDto->cost = $basketItem->qty > 0 ? $basketItem->price / $basketItem->qty : 0;
                }
            }
        }
        
        return $deliveryOrderInputDto;
    }
}
