<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Basket\Basket;
use Faker\Generator as Faker;

$factory->define(Basket::class, function (Faker $faker) {
    return [
        'customer_id' => $faker->randomNumber(),
        'is_belongs_to_order' => true,
        'type' => Basket::TYPE_PRODUCT,
    ];
});
