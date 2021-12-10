<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Basket\Basket;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\OrderStatus;
use Faker\Generator as Faker;
use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;

$factory->define(Order::class, function (Faker $faker) {
    $basket = factory(Basket::class)->create();
    $basketItems = $basket->items;
    $basketItemsCost = $basketItems->sum('cost');
    $basketItemsPrice = $basketItems->sum('price');

    $deliveryCost = $faker->numberBetween(0, 500);
    $deliveryPrice = $faker->numberBetween(0, $deliveryCost / 2);
    $cost = $basketItemsCost + $deliveryCost;
    $price = $basketItemsPrice + $deliveryPrice;

    return [
        'basket_id' => $basket->id,
        'customer_id' => $faker->randomNumber(),
        'type' => Basket::TYPE_PRODUCT,
        'status' => $faker->randomElement([OrderStatus::CREATED, OrderStatus::AWAITING_CHECK, OrderStatus::CHECKING, OrderStatus::AWAITING_CONFIRMATION]),
        'created_at' => $faker->dateTimeThisYear(),
        'delivery_type' => $faker->randomElement(DeliveryType::validValues()),
        'delivery_cost' => $deliveryCost,
        'delivery_price' => $deliveryPrice,
        'cost' => $cost,
        'price' => $price,
        'spent_certificate' => 0,
        'is_require_check' => $faker->boolean(),
        'payment_status' => $faker->randomElement(PaymentStatus::validValues()),
    ];
});
