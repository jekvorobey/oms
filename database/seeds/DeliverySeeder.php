<?php

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryType;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Store\Dto\StoreDto;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Pim\Core\PimException;

/**
 * Class DeliverySeeder
 */
class DeliverySeeder extends Seeder
{
    /** @var int */
    const FAKER_SEED = 123456;

    /**
     * @throws PimException
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);
    
        /** @var StoreService $storeService */
        $storeService = resolve(StoreService::class);
        $restQuery = $storeService->newQuery();
        $restQuery->addFields(StoreDto::entity(), 'id', 'merchant_id');
        /** @var Collection|StoreDto[] $stores */
        $stores = $storeService->stores($restQuery)->keyBy('id');
    
        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        $tariffs = $listsService->tariffs()->groupBy('delivery_service');
        $points = $listsService->points()->groupBy('delivery_service');
        
        /** @var Collection|Order[] $orders */
        $orders = Order::query()->with('basket', 'basket.items')->get();
        foreach ($orders as $order) {
            $basketItemsByStore = $order->basket->items->groupBy('product.store_id');
            
            /** @var int $deliveriesCount - кол-во доставок в заказе (от 1 до кол-ва складов = отправлений) */
            $deliveriesCount = $order->delivery_type == DeliveryType::TYPE_SPLIT ?
                $faker->numberBetween(1, $basketItemsByStore->count()) : 1;
            /** @var int $shipmentsCount - кол-во отправлений в доставке */
            $shipmentsCount = (int)$basketItemsByStore->count()/$deliveriesCount;
    
            $shipmentNumber = 1;
            for ($i = 1; $i <= $deliveriesCount; $i++) {
                //Создаем доставку
                $delivery = new Delivery();
                $delivery->order_id = $order->id;
                $delivery->delivery_method = $faker->randomElement(array_keys(DeliveryMethod::allMethods()));
                $delivery->delivery_service = $faker->randomElement([
                    DeliveryService::SERVICE_B2CPL,
                ]);
                $delivery->tariff_id = isset($tariffs[$delivery->delivery_service]) ?
                    $faker->randomElement($tariffs[$delivery->delivery_service]->pluck('id')->toArray()) : 0;
                $delivery->point_id = (
                    isset($points[$delivery->delivery_service]) &&
                    in_array($delivery->delivery_method, [DeliveryMethod::METHOD_OUTPOST_PICKUP, DeliveryMethod::METHOD_POSTOMAT_PICKUP])) ?
                    $faker->randomElement($points[$delivery->delivery_service]->pluck('id')->toArray()) : 0;
                $delivery->xml_id = '';
                $delivery->number = $order->number . '-' . $i;
                $delivery->cost = $faker->randomFloat(2, 0, 500);
                $delivery->delivery_at = $order->created_at->modify('+' . rand(1, 7) . ' days');
                $delivery->created_at = $order->created_at;
                $delivery->save();
    
                $deliveryShipmentNumber = 1;
                foreach ($basketItemsByStore as $storeId => $itemsByStore) {
                    if (!$storeId) {
                        continue;
                    }
                    if ($deliveryShipmentNumber > $shipmentsCount && $i != $deliveriesCount ) {
                        break;
                    }
                    
                    //Создаем отправление
                    /** @var Collection|BasketItem[] $itemsByStore */
                    $store = $stores[$storeId];
                    $shipment = new Shipment();
                    $shipment->delivery_id = $delivery->id;
                    $shipment->merchant_id = $store->merchant_id;
                    $shipment->store_id = $storeId;
                    $shipment->number = $order->number . '/' . $shipmentNumber;
                    $shipment->created_at = $order->created_at->modify('+' . rand(1, 7) . ' minutes');
                    $shipment->required_shipping_at = $order->created_at->modify('+3 hours');
                    $shipment->save();
                    
                    foreach ($itemsByStore as $item) {
                        //Создаем состав отправления
                        $shipmentItem = new ShipmentItem();
                        $shipmentItem->shipment_id = $shipment->id;
                        $shipmentItem->basket_item_id = $item->id;
                        $shipmentItem->created_at = $shipment->created_at;
                        $shipmentItem->save();
                    }
    
                    $shipmentNumber++;
                    $deliveryShipmentNumber++;
                    $basketItemsByStore->forget($storeId);
                }
            }
        }
    }
}
