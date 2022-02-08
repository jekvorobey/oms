<?php

namespace Tests\Feature;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\AnalyticsService\AnalyticsDateInterval;
use App\Services\AnalyticsService\AnalyticsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Faker\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use WithoutEvents, RefreshDatabase;

    private const PERIODS = [
        'current' => [],
        'previous' => [],
    ];

    private $merchantId = 1;

    public function provider(): array
    {
        $date = Carbon::now()->subMonth();
        return [
            [
                [
                    'merchantId' => $this->merchantId,
                    'start' => $date->clone()->startOfMonth(),
                    'end' => $date->clone()->endOfMonth(),
                    'intervalType' => AnalyticsDateInterval::TYPE_MONTH,
                ],
            ],
            [
                [
                    'merchantId' => $this->merchantId,
                    'start' => $date->clone()->startOfYear(),
                    'end' => $date->clone()->endOfYear(),
                    'intervalType' => AnalyticsDateInterval::TYPE_YEAR,
                ],
            ],
            [
                [
                    'merchantId' => $this->merchantId,
                    'start' => $date->clone()->startOfWeek(),
                    'end' => $date->clone()->endOfWeek(CarbonInterface::SUNDAY),
                    'intervalType' => AnalyticsDateInterval::TYPE_WEEK,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testAnalytics($data): void
    {
        $expectedData = $this->seedDB($data['intervalType'], $data['start'], $data['end']);
        $requestData = $data;
        $requestData['start'] = $data['start']->format('Y-m-d');
        $requestData['end'] = $data['end']->format('Y-m-d');

        $response = [];
        $urlQueryString = '?' . http_build_query($requestData);
        $urlBase = '/api/v1';

        $response['shipments'] = $this->get("$urlBase/merchant_analytics/products_shipments$urlQueryString");
        $response['sales'] = $this->get("$urlBase/merchant_analytics/sales$urlQueryString");
        $response['bestsellers'] = $this->get("$urlBase/merchant_analytics/top/bestsellers$urlQueryString");

        foreach (array_keys($expectedData) as $key) {
            $this->assertEquals(200, $response[$key]->getStatusCode(), "$key request returns returns wrong status code");
            $response[$key] = json_decode($response[$key]->getContent(), true);
            if ($key === 'sales') {
                foreach (array_keys(self::PERIODS) as $period) {
                    foreach ($expectedData[$key][$period] as $dKey => $datum) {
                        $this->assertEquals($datum, $response[$key][$period][$dKey], "requested analytics: $key | period: $period, dataKey: $dKey");
                    }
                }
            } else {
                foreach ($expectedData[$key] as $dKey => $datum) {
                    $this->assertEquals($datum, $response[$key][$dKey], "requested analytics: $key | dataKey: $dKey");
                }
            }
        }
    }

    private function seedDB(string $intervalType, Carbon $start, Carbon $end): array
    {
        $faker = Factory::create('ru_RU');

        $testBasketItemsData = collect([]);
        for ($i = 1; $i <= 20; $i++) {
            $testBasketItemsData->push([
                'name' => "productTestName$i",
                'price' => $faker->numberBetween(500, 30000),
                'offer_id' => $i,
            ]);
        }
        /** @var Basket[]|Collection $baskets */
        $baskets = factory(Basket::class, 200)->create();

        $shipmentTemplateData = [
            'sum' => 0,
            'oldSum' => 0,
            'countShipments' => 0,
            'countProducts' => 0,
        ];

        $salesTemplateData = [
            'intervalNumber' => null,
            'sum' => 0,
        ];

        $expectedData = [
            'shipments' => [
                AnalyticsService::STATUS_ACCEPTED => $shipmentTemplateData,
                AnalyticsService::STATUS_SHIPPED => $shipmentTemplateData,
                AnalyticsService::STATUS_TRANSITION => $shipmentTemplateData,
                AnalyticsService::STATUS_DONE => $shipmentTemplateData,
                AnalyticsService::STATUS_CANCELED => $shipmentTemplateData,
                AnalyticsService::STATUS_RETURNED => $shipmentTemplateData,
            ],
            'sales' => self::PERIODS,
            'bestsellers' => self::PERIODS,
        ];

        foreach ($baskets as $bIdx => $basket) {
            $isCurrentPeriod = $bIdx < 100;
            $periodKey = $isCurrentPeriod ? 'current' : 'previous';

            $orderAt = Carbon::now();

            if ($isCurrentPeriod) {
                $shipmentAt = Carbon::createFromTimestamp($faker->numberBetween($start->unix(), $end->unix()));
            } else {
                $shipmentAt = Carbon::createFromTimestamp(
                    $faker->numberBetween(
                        $start->clone()->sub(1, $intervalType)->startOfDay()->unix(),
                        $start->clone()->subDay()->endOfDay()->unix()
                    )
                );
            }

            $statuses = [
                AnalyticsService::STATUS_CANCELED => $faker->numberBetween(ShipmentStatus::AWAITING_CONFIRMATION, ShipmentStatus::DELIVERING),
                AnalyticsService::STATUS_SHIPPED => ShipmentStatus::SHIPPED,
                AnalyticsService::STATUS_TRANSITION => $faker->numberBetween(ShipmentStatus::ON_POINT_IN, ShipmentStatus::DELIVERING),
                AnalyticsService::STATUS_DONE => ShipmentStatus::DONE,
                AnalyticsService::STATUS_RETURNED => ShipmentStatus::CANCELLATION_EXPECTED,
                AnalyticsService::STATUS_ACCEPTED => $faker->numberBetween(ShipmentStatus::AWAITING_CONFIRMATION, ShipmentStatus::ASSEMBLED),
            ];
            $sIdx = array_rand($statuses);

            /** @var BasketItem[]|Collection $basketItems */
            $basketItems = new Collection();
            $bsData = &$expectedData['bestsellers'][$periodKey];
            for ($i = 1; $i <= $faker->numberBetween(1, 3); $i++) {
                $randomBasketItem = $testBasketItemsData->random();
                $basketItemData = [
                    'basket_id' => $basket->id,
                    'qty' => $faker->numberBetween(1, 5),
                ] + $randomBasketItem;
                $sum = $basketItemData['price'] * $basketItemData['qty'];

                if (!isset($bsData[$randomBasketItem['offer_id']])) {
                    $bsData[$randomBasketItem['offer_id']] = [
                        'name' => $randomBasketItem['name'],
                        'offerId' => $randomBasketItem['offer_id'],
                        'sum' => $sum,
                        'count' => $basketItemData['qty'],
                    ];
                } else {
                    $bsData[$randomBasketItem['offer_id']]['sum'] += $sum;
                    $bsData[$randomBasketItem['offer_id']]['count'] += $basketItemData['qty'];
                }
                $basketItems->push(factory(BasketItem::class)->create($basketItemData));
            }

            $basketItemsCost = $basketItems->sum('cost');
            $basketItemsQty = $basketItems->sum('qty');
            $basketItemsSum = $basketItems->sum(fn(BasketItem $item) => $item->price * $item->qty);

            $sumIdx = $isCurrentPeriod ? 'sum' : 'oldSum';

            if ($isCurrentPeriod) {
                $expectedData['shipments'][$sIdx]['countShipments']++;
                $expectedData['shipments'][$sIdx]['countProducts'] += $basketItemsQty;
            }
            $expectedData['shipments'][$sIdx][$sumIdx] += $basketItemsSum;
            if ($sIdx !== AnalyticsService::STATUS_ACCEPTED) {
                if ($isCurrentPeriod) {
                    $expectedData['shipments'][AnalyticsService::STATUS_ACCEPTED]['countShipments']++;
                    $expectedData['shipments'][AnalyticsService::STATUS_ACCEPTED]['countProducts'] += $basketItemsQty;
                }
                $expectedData['shipments'][AnalyticsService::STATUS_ACCEPTED][$sumIdx] += $basketItemsSum;
            }
            $intervalNumber = $shipmentAt->{AnalyticsDateInterval::TYPES[$intervalType]['groupBy']};
            if ($statuses[$sIdx] === ShipmentStatus::DONE) {
                if (!isset($expectedData['sales'][$periodKey][$intervalNumber])) {
                    $expectedData['sales'][$periodKey][$intervalNumber] = $salesTemplateData;
                    $expectedData['sales'][$periodKey][$intervalNumber]['intervalNumber'] = $intervalNumber;
                    $expectedData['sales'][$periodKey][$intervalNumber]['sum'] = $basketItemsSum;
                } else {
                    $expectedData['sales'][$periodKey][$intervalNumber]['sum'] += $basketItemsSum;
                }
            }

            $deliveryCost = $faker->numberBetween(0, 500);
            $deliveryPrice = $faker->numberBetween(0, $deliveryCost / 2);
            /** @var Order $order */
            $order = factory(Order::class)->create([
                'basket_id' => $basket->id,
                'number' => $faker->numberBetween(1, 50),
                'delivery_cost' => $deliveryCost,
                'delivery_price' => $deliveryPrice,
                'cost' => $basketItemsCost + $deliveryCost,
                'price' => $basketItemsSum + $deliveryPrice,
                'payment_status' => PaymentStatus::PAID,
                'created_at' => $orderAt,
            ]);

            factory(Payment::class)->create([
                'order_id' => $order->id,
                'sum' => $basketItemsCost + $deliveryCost,
                'status' => PaymentStatus::PAID,
            ]);

            /** @var Delivery $delivery */
            $delivery = factory(Delivery::class)->create([
                'order_id' => $order->id,
                'tariff_id' => 0,
                'number' => Delivery::makeNumber($order->number, 1),
                'cost' => $deliveryCost,
                'dt' => $faker->randomFloat(0, 1, 7),
                'delivery_at' => $orderAt,
                'pdd' => $orderAt,
            ]);
            /** @var Shipment $shipment */
            $shipment = factory(Shipment::class)->create([
                'delivery_id' => $delivery->id,
                'merchant_id' => $this->merchantId,
                'psd' => $delivery->created_at->modify('+' . $faker->randomFloat(0, 120, 300) . ' minutes'),
                'store_id' => 1,
                'number' => Shipment::makeNumber((int) $delivery->order->number, 1, 1),
                'created_at' => $shipmentAt,
                'required_shipping_at' => $delivery->delivery_at->modify('+3 hours'),
                'status' => $statuses[$sIdx],
                'status_at' => $shipmentAt,
                'payment_status' => PaymentStatus::PAID,
                'is_canceled' => $sIdx === AnalyticsService::STATUS_CANCELED,
            ]);

            foreach ($basketItems as $basketItem) {
                factory(ShipmentItem::class)->create([
                    'shipment_id' => $shipment->id,
                    'basket_item_id' => $basketItem->id,
                ]);
            }
        }

        $currentBestsellers = collect($expectedData['bestsellers']['current']);
        $previousBestsellers = $expectedData['bestsellers']['previous'];
        foreach ($currentBestsellers as $offerId => $bestseller) {
            if (isset($previousBestsellers[$offerId])) {
                $currentBestsellers[$offerId] += ['lfl' => $this->lfl($bestseller['sum'], $previousBestsellers[$offerId]['sum'])];
            }
        }
        $expectedData['bestsellers'] = $currentBestsellers->sortByDesc('sum')->slice(0, 10)->values()->toArray();

        foreach ($expectedData['shipments'] as $status => $datum) {
            $expectedData['shipments'][$status]['lfl'] = $this->lfl($datum['sum'], $expectedData['shipments'][$status]['oldSum']);
            unset($expectedData['shipments'][$status]['oldSum']);
        }
        foreach (array_keys($expectedData['sales']) as $key) {
            sort($expectedData['sales'][$key]);
            $expectedData['sales'][$key] = array_values($expectedData['sales'][$key]);
        }
        return $expectedData;
    }

    private function lfl(int $currentSum, int $prevSum): int
    {
        if ($prevSum === 0) {
            return 100;
        }
        $diff = $currentSum - $prevSum;
        return (int) ( $diff / $prevSum * 100);
    }
}
