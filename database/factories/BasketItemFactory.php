<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use App\Models\Basket\BasketItem;
use App\Models\Basket\Basket;

$factory->define(BasketItem::class, function (Faker $faker) {
    $price = $faker->numberBetween(100, 5000);
    $qty = $faker->numberBetween(1, 6);
    $cost = $price * $qty;

    return [
        'basket_id' => function () {
            return factory(Basket::class)->create()->id;
        },
        'offer_id' => $faker->randomNumber(),
        'type' => Basket::TYPE_PRODUCT,
        'name' => $faker->title,
        'qty' => $qty,
        'price' => $price,
        'cost' => $cost,
        'product' => [
            'store_id' => $faker->numberBetween(1, 6),
            'weight' => $faker->numberBetween(1, 100),
            'width' => $faker->numberBetween(1, 100),
            'height' => $faker->numberBetween(1, 100),
            'length' => $faker->numberBetween(1, 100),
            'merchant_id' => $faker->numberBetween(1, 100),
            'is_danger' => $faker->boolean,
        ],
    ];
});
