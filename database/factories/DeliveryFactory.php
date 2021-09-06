<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Delivery\DeliveryStatus;
use Faker\Generator as Faker;
use App\Models\Delivery\Delivery;
use App\Models\Order\Order;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;

$factory->define(Delivery::class, function (Faker $faker) {
    $order = factory(Order::class)->create();
    $deliveryDt = $faker->randomFloat(0, 1, 7);
    $deliveryAt = $order->created_at->modify('+' . $deliveryDt . ' days')->setTime(0, 0);

    return [
        'order_id' => $order->id,
        'delivery_method' => $faker->randomElement(array_keys(DeliveryMethod::allMethods())),
        'delivery_service' => $faker->randomElement([
            LogisticsDeliveryService::SERVICE_B2CPL,
        ]),
        'status' => DeliveryStatus::CREATED,
        'tariff_id' => 0,
        'number' => Delivery::makeNumber($order->number, 1),
        'cost' => $faker->randomFloat(2, 0, 500),
        'dt' => $deliveryDt,
        'delivery_at' => $deliveryAt,
        'pdd' => $deliveryAt,
    ];
});
