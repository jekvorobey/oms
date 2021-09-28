<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Delivery\CargoStatus;
use Faker\Generator as Faker;
use App\Models\Delivery\Cargo;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;

$factory->define(Cargo::class, function (Faker $faker) {
    return [
        'merchant_id' => $faker->randomNumber(),
        'store_id' => 1,
        'status' => $faker->randomElement(CargoStatus::validValues()),
        'delivery_service' => $faker->randomElement(array_keys(LogisticsDeliveryService::allServices())),
        'xml_id' => $faker->uuid,
        'width' => $faker->randomFloat(3, 1, 6),
        'height' => $faker->randomFloat(3, 1, 6),
        'length' => $faker->randomFloat(3, 1, 6),
        'weight' => $faker->randomFloat(3, 1, 6),
    ];
});
