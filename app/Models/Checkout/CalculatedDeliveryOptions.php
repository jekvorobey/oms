<?php

namespace App\Models\Checkout;

use App\Models\Cart\CartManager;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketInput\DeliveryBasketInputDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketInput\DeliveryStoreDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketItemDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketOutput\DeliveryBasketOutputDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketOutput\DeliveryShipmentDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketOutput\DeliveryTariffItemDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketOutput\TariffDto;
use Greensight\Logistics\Dto\Lists\DeliveryMethod as LogisticsDeliveryMethod;
use Greensight\Logistics\Services\CalculatorService\CalculatorService;
use Greensight\Oms\Dto\Delivery\ShipmentItemDto;

class CalculatedDeliveryOptions
{
    /** @var CalculatorService */
    private $calculatorService;
    /** @var CheckoutDataDto */
    private $checkout;
    /** @var CartManager */
    private $cartManager;
    /** @var array */
    private $deliveryTariffs;
    /** @var array */
    private $pickupTariffs;
    
    
    public function __construct(CheckoutDataDto $checkout, CartManager $cartManager)
    {
        $this->calculatorService = resolve(CalculatorService::class);
        $this->checkout = $checkout;
        $this->cartManager = $cartManager;
    }
    
    public function getCourierDeliveries()
    {
        return array_map(function (array $tariffs) {
            // one courier tariff is one delivery, always.. maybe
            return current($tariffs);
        }, $this->deliveryTariffs);
    }
    
    public function getPickupDeliveries() {
        return array_map(function (array $tariffs) {
//            foreach ($tariffs as $tariff) {
//                [
//                    'id' => $id,
//                    'deliveryIndex' => $deliveryIndex,
//                    'deliveryService' => $delivery_service,
//                    'shipments' => $shipments,
//
//                    'point_ids' => $pointIds,
//                    'days_min' => $tariffItem->days_min,
//                    'days_max' => $tariffItem->days_max,
//                ] = $tariff;
//            }
        }, $this->pickupTariffs);
    }
    
    public function deliveries()
    {
        $this->calculate();
        
        echo "banana";

//        $methodDelivery = new DeliveryMethod(self::RECEIVE_METHOD_DELIVERY, 'Доставка курьером');
//        $methodDelivery->setInfo(500, 'Ближайшая доставка в понедельник, 29 декабря');
//        $methodDelivery->addOption(LogisticsDeliveryMethod::METHOD_DELIVERY, 'Доставка');
//
//        $methodPickup = new DeliveryMethod(self::RECEIVE_METHOD_PICKUP, 'Самовывоз из 12 пунктов');
//        $methodPickup->setInfo(200, 'Ближайший самовывоз в четверг, 3 декабря');
//        $methodPickup->addOption(LogisticsDeliveryMethod::METHOD_OUTPOST_PICKUP, 'Outpost');
//        $methodPickup->addOption(LogisticsDeliveryMethod::METHOD_POSTOMAT_PICKUP, 'Postomat');
//
//        $checkout->receiveMethods = [
//            $methodDelivery,
//            $methodPickup
//        ];
    }
    
    private function calculate(): void
    {
        $calcIn = new DeliveryBasketInputDto();
        $calcIn->city_guid_to = 'c52ea942-555e-45c6-9751-58897717b02f';
        
        $calcItems = [];
        $storeIds = [];
        $basket = $this->cartManager->originalBasket();
        foreach ($basket->items() as $basketItem) {
            $calcItem = new DeliveryBasketItemDto();
            $calcItem->offer_id = $basketItem->offer_id;
            $calcItem->qty = $basketItem->qty;
            $calcItem->store_id = $basketItem->product['store_id'];
            $calcItem->width = $basketItem->product['width'];
            $calcItem->height = $basketItem->product['height'];
            $calcItem->length = $basketItem->product['length'];
            $calcItem->weight = $basketItem->product['weight'];
            
            $calcItems[] = $calcItem;
            
            $storeIds[$calcItem->store_id] = $calcItem->store_id;
        }
        $calcIn->basket_items = $calcItems;
        
        $calcStores = [];
        foreach ($storeIds as $storeId) {
            $calcStore = new DeliveryStoreDto();
            $calcStore->store_id = $storeId;
            $calcStore->city_guid = 'c52ea942-555e-45c6-9751-58897717b02f';
            $calcStores[] = $calcStore;
        }
        $calcIn->stores = $calcStores;
    
        $calcResult = $this->calculatorService->calculate($calcIn);
    
        $deliveryTariffs = [];
        $pickupTariffs = [];
    
        foreach ($calcResult->deliveries as $deliveryIndex => $calcDelivery) {
            $deliveryTariffs[$deliveryIndex] = [];
            $pickupTariffs[$deliveryIndex] = [];
            
            $tariff = $calcDelivery->delivery_tariff;
            $shipments = [];
            foreach ($calcDelivery->shipments as $calcShipment) {
                $shipments[] = [
                    'store_id' => $calcShipment->store_id,
                    'weight' => $calcShipment->weight,
                    'height' => $calcShipment->height,
                    'length' => $calcShipment->length,
                    'width' => $calcShipment->width,
                    'items' => $calcShipment->items->map(function (DeliveryBasketItemDto $item) {
                        return [
                            'offer_id' => $item->offer_id,
                            'qty' => $item->qty,
                        ];
                    })->all()
                ];
            }
            // delivery part ====================================
            $tariffDelivery = $tariff->delivery_to_door;
            /** @var TariffDto $tariffItem */
            foreach ($tariffDelivery->tariffs as $tariffItem) {
                $deliveryTariff = [
                    'id' => $tariffItem->xml_id,
                    'deliveryIndex' => $deliveryIndex,
                    'deliveryService' => $tariffDelivery->delivery_service,
                    'name' => $tariffItem->name,
                    'service_cost' => $tariffItem->delivery_service_cost,
                    'cost' => $calcResult->delivery_to_door_cost,
                    'dates' => $tariffItem->available_dates->pluck('date')->all(),
                    'shipments' => $shipments
                ];
            
                $deliveryTariffs[$deliveryIndex][] = $deliveryTariff;
            }
            // pickup part =====================================
            $tariffPickup = $tariff->delivery_to_point;
            foreach ($tariffPickup->tariffs as $tariffItem) {
                if (!$tariffItem->point_ids) {
                    continue;
                }
                $pickupTariff = [
                    'id' => $tariffItem->xml_id,
                    'name' => $tariffItem->name,
                    'deliveryIndex' => $deliveryIndex,
                    'deliveryService' => $tariffDelivery->delivery_service,
                    'service_cost' => $tariffItem->delivery_service_cost,
                    'cost' => $calcResult->delivery_to_point_cost,
                    'shipments' => $shipments,
                
                    'point_ids' => $tariffItem->point_ids,
                    'days_min' => $tariffItem->days_min,
                    'days_max' => $tariffItem->days_max,
                ];
                $pickupTariffs[$deliveryIndex][] = $pickupTariff;
            }
        }
        
        $this->deliveryTariffs = $deliveryTariffs;
        $this->pickupTariffs = $pickupTariffs;
    }
}
