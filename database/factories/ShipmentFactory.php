<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Delivery\ShipmentStatus;
use Faker\Generator as Faker;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\Delivery;

$factory->define(Shipment::class, function (Faker $faker) {
    $delivery = factory(Delivery::class)->create();
    return [
        'status' => $faker->randomElement(ShipmentStatus::validValues()),
        'delivery_id' => $delivery->id,
        'merchant_id' => $faker->randomNumber(),
        'psd' => $delivery->created_at->modify('+' . $faker->randomFloat(0, 120, 300) . ' minutes'),
        'store_id' => 1,
        'number' => Shipment::makeNumber((int) $delivery->order->number, 1, 1),
        'created_at' => $delivery->delivery_at->modify('+' . random_int(1, 7) . ' minutes'),
        'required_shipping_at' => $delivery->delivery_at->modify('+3 hours'),
    ];
});
