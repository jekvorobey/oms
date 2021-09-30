<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\Shipment;
use App\Models\Basket\BasketItem;

$factory->define(ShipmentItem::class, function (Faker $faker) {
    return [
        'shipment_id' => function () {
            return factory(Shipment::class)->create()->id;
        },
        'basket_item_id' => function () {
            return factory(BasketItem::class)->create()->id;
        },
        'created_at' => $faker->dateTime,
    ];
});
