<?php

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryType;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use App\Services\DeliveryService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\B2CplDeliveryOrderStatus;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\DeliveryOrderStatus;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
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
    /** @var array */
    const REAL_ADDRESSES = [
        [
            'country_code' => 'RU',
            'post_index' => '124482',
            'region' => 'Москва',
            'region_guid' => '0c5b2444-70a0-4932-980c-b4dc0d3f02b5',
            'area' => '',
            'area_guid' => '',
            'city' => 'г Зеленоград',
            'city_guid' => 'ec44c0ee-bf24-41c8-9e1c-76136ab05cbf',
            'street' => '',
            'house' => 'к 305',
            'block' => '',
            'flat' => 'офис 301',
            'porch' => '',
            'floor' => '3',
            'intercom' => '',
            'comment' => '',
        ],
        [
            'country_code' => 'RU',
            'post_index' => '170034',
            'region' => 'Тверская обл',
            'region_guid' => '61723327-1c20-42fe-8dfa-402638d9b396',
            'area' => '',
            'area_guid' => '',
            'city' => 'г Тверь',
            'city_guid' => 'c52ea942-555e-45c6-9751-58897717b02f',
            'street' => 'ул Дарвина',
            'house' => 'д 1',
            'block' => '',
            'flat' => 'кв 1',
            'porch' => '',
            'floor' => '1',
            'intercom' => '',
            'comment' => '',
        ],
        [
            'country_code' => 'RU',
            'post_index' => '141503',
            'region' => 'Московская обл',
            'region_guid' => '29251dcf-00a1-4e34-98d4-5c47484a36d4',
            'area' => 'Солнечногорский р-н',
            'area_guid' => '395203ad-a8a5-4708-944c-790ec93bf8a3',
            'city' => 'г Солнечногорск',
            'city_guid' => 'd4dadcfd-355d-4fe9-963d-48ad122a7778',
            'street' => 'ул Красная',
            'house' => 'д 120',
            'block' => '',
            'flat' => 'кв 23',
            'porch' => '1',
            'floor' => '4',
            'intercom' => '23',
            'comment' => '',
        ],
        [
            'country_code' => 'RU',
            'post_index' => '141503',
            'region' => 'Московская обл',
            'region_guid' => '29251dcf-00a1-4e34-98d4-5c47484a36d4',
            'area' => 'Солнечногорский р-н',
            'area_guid' => '395203ad-a8a5-4708-944c-790ec93bf8a3',
            'city' => 'г Солнечногорск',
            'city_guid' => 'd4dadcfd-355d-4fe9-963d-48ad122a7778',
            'street' => 'ул Красная',
            'house' => 'д 120',
            'block' => '',
            'flat' => 'кв 23',
            'porch' => '1',
            'floor' => '5',
            'intercom' => '23',
            'comment' => '',
        ],
        [
            'country_code' => 'RU',
            'post_index' => '420036',
            'region' => 'Респ Татарстан',
            'region_guid' => '0c089b04-099e-4e0e-955a-6bf1ce525f1a',
            'area' => '',
            'area_guid' => '',
            'city' => 'г Казань',
            'city_guid' => '93b3df57-4c89-44df-ac42-96f05e9cd3b9',
            'street' => 'ул Ульяны Громовой',
            'house' => 'д 3',
            'block' => '',
            'flat' => 'кв 12',
            'porch' => '2',
            'floor' => '1',
            'intercom' => '12',
            'comment' => '',
        ],
    ];

    /**
     * @throws PimException
     * @throws Exception
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        /** @var DeliveryService $deliveryService */
        $deliveryService = resolve(DeliveryService::class);

        /** @var StoreService $storeService */
        $storeService = resolve(StoreService::class);
        $restQuery = $storeService->newQuery();
        $restQuery->addFields(StoreDto::entity(), 'id', 'merchant_id');
        /** @var Collection|StoreDto[] $stores */
        $stores = $storeService->stores($restQuery)->keyBy('id');

        /** @var PackageService $packageService */
        $packageService = resolve(PackageService::class);
        $packages = $packageService->packages($packageService->newQuery()
            ->setFilter('type', PackageType::TYPE_BOX));

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
                    LogisticsDeliveryService::SERVICE_B2CPL,
                ]);
                switch($delivery->delivery_service) {
                    case LogisticsDeliveryService::SERVICE_B2CPL:
                        $delivery->setStatusXmlId($faker->randomElement(array_keys(B2CplDeliveryOrderStatus::allStatuses())));
                        $b2cplStatus = B2CplDeliveryOrderStatus::statusById($delivery->status_xml_id);
                        if (isset($b2cplStatus['delivery_status_id'])) {
                            $delivery->setStatus($b2cplStatus['delivery_status_id']);
                        } else {
                            $delivery->setStatus($faker->randomElement(array_keys(DeliveryOrderStatus::allStatuses())));
                        }
                        break;
                }
                $delivery->tariff_id = isset($tariffs[$delivery->delivery_service]) ?
                    $faker->randomElement($tariffs[$delivery->delivery_service]->pluck('id')->toArray()) : 0;

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
                    $delivery->delivery_address = $faker->randomElement(static::REAL_ADDRESSES);
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
                    $shipment->status = $faker->randomElement(ShipmentStatus::validValues());
                    $shipment->delivery_id = $delivery->id;
                    $shipment->merchant_id = $store->merchant_id;
                    $shipment->store_id = $storeId;
                    $shipment->number = Shipment::makeNumber($order->number, $shipmentNumber);
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
                    if ($shipment->status > ShipmentStatus::AWAITING_CONFIRMATION) {
                        $shipmentPackage = $deliveryService->createShipmentPackage(
                            $shipment->id,
                            $faker->randomElement($packages->pluck('id')->all())
                        );

                        if ($shipmentPackage) {
                            foreach ($shipment->basketItems as $basketItem) {
                                $deliveryService->setShipmentPackageItem(
                                    $shipmentPackage->id,
                                    $basketItem->id,
                                    $basketItem->qty,
                                    0
                                );
                            }
                        }
                    }

                    //Добавляем собранные отправления в груз
                    try {
                        $deliveryService->addShipment2Cargo($shipment->id);
                    } catch (Exception $e) {
                    }
                }

                //Создаем заказ на доставку у службы доставки
                try {
                    $deliveryService->saveDeliveryOrder($delivery);
                } catch (Exception $e) {
                }
            }
        }
    }
}
