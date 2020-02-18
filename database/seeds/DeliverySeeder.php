<?php

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryType;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\B2CplDeliveryOrderStatus;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\DeliveryOrderStatus;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Store\Dto\Package\PackageType;
use Greensight\Store\Dto\StoreDto;
use Greensight\Store\Services\PackageService\PackageService;
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

        /** @var PackageService $packageService */
        $packageService = resolve(PackageService::class);
        $packages = $packageService->packages($packageService->newQuery()
            ->setFilter('type', PackageType::TYPE_BOX))->keyBy('id');
    
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
            for ($deliveryNum = 1; $deliveryNum <= $deliveriesCount; $deliveryNum++) {
                //Создаем доставку
                $delivery = new Delivery();
                $delivery->order_id = $order->id;
                $delivery->delivery_method = $faker->randomElement(array_keys(DeliveryMethod::allMethods()));
                $delivery->delivery_service = $faker->randomElement([
                    DeliveryService::SERVICE_B2CPL,
                ]);
                $delivery->setStatus($faker->randomElement(array_keys(DeliveryOrderStatus::allStatuses())));
                switch($delivery->delivery_service) {
                    case DeliveryService::SERVICE_B2CPL:
                        $delivery->setStatusXmlId($faker->randomElement(array_keys(B2CplDeliveryOrderStatus::allStatuses())));
                        break;
                }
                $delivery->tariff_id = isset($tariffs[$delivery->delivery_service]) ?
                    $faker->randomElement($tariffs[$delivery->delivery_service]->pluck('id')->toArray()) : 0;
               
                $delivery->xml_id = $delivery->status > DeliveryOrderStatus::STATUS_CREATED ? $faker->uuid : '';
                $delivery->number = Delivery::makeNumber($order->number, $deliveryNum);
                $delivery->cost = $faker->randomFloat(2, 0, 500);
                $delivery->delivery_at = $order->created_at->modify('+' . rand(1, 7) . ' days');
                $delivery->created_at = $order->created_at;

                $delivery->receiver_name = $faker->name;
                $delivery->receiver_phone = $faker->phoneNumber;
                $delivery->receiver_email = $faker->email;
                
                if (
                    isset($points[$delivery->delivery_service]) &&
                    $delivery->delivery_method == DeliveryMethod::METHOD_PICKUP
                ) {
                    $delivery->point_id = $faker->randomElement($points[$delivery->delivery_service]->pluck('id')->toArray());
                } else {
                    $region = $faker->randomElement([
                        'Москва г',
                        'Московская обл',
                        'Тверская обл',
                        'Калужская обл',
                        'Рязанская обл',
                    ]);
                    $delivery->delivery_address = [
                        'country_code' => 'RU',
                        'post_index' => $faker->postcode,
                        'region' => $region,
                        'region_guid' => $faker->uuid,
                        'area' => '',
                        'area_guid' => '',
                        'city' => 'г. ' . $faker->city,
                        'city_guid' => $faker->uuid,
                        'street' => 'ул. ' . explode(' ', $faker->streetName)[0],
                        'house' => 'д. ' . $faker->buildingNumber,
                        'block' => '',
                        'flat' => '',
                        'porch' => '',
                        'intercom' => '',
                        'comment' => '',
                    ];
                }
                $delivery->save();
    
                $deliveryShipmentNumber = 1;
                foreach ($basketItemsByStore as $storeId => $itemsByStore) {
                    if (!$storeId) {
                        continue;
                    }
                    if ($deliveryShipmentNumber > $shipmentsCount && $deliveryNum != $deliveriesCount ) {
                        break;
                    }
                    
                    //Создаем отправление
                    /** @var Collection|BasketItem[] $itemsByStore */
                    $store = $stores[$storeId];
                    $shipment = new Shipment();
                    $shipment->delivery_id = $delivery->id;
                    $shipment->merchant_id = $store->merchant_id;
                    $shipment->store_id = $storeId;
                    $shipment->number = Shipment::makeNumber($delivery->number, $shipmentNumber);
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

                    //Создаем коробки для отправлений в сборке или собранных
                    if ($shipment->status > ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE) {
                        /** @var int $shipmentPackagesCount - количество коробок в отправлении */
                        $shipmentPackagesCount = $faker->randomFloat(0, 1, 3);
                        for ($shipmentPackageNum = 1; $shipmentPackageNum <= $shipmentPackagesCount; $shipmentPackageNum++) {
                            $shipmentPackage = $shipment->createPackage($faker->randomElement($packages->pluck('id')->all()));
                            //todo Доделать создание содержимого коробок
                        }
                    }
                }
            }
        }
    }
}
