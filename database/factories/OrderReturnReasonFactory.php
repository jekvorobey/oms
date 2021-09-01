<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Order\OrderReturnReason;
use Faker\Generator as Faker;

$factory->define(OrderReturnReason::class, function (Faker $faker) {
    return [
        'text' => $faker->text
    ];
});
