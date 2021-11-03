<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use App\Models\Payment\Payment;
use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Models\Payment\PaymentMethod;

$factory->define(Payment::class, function (Faker $faker) {
    $order = factory(Order::class)->create();

    return [
        'order_id' => $order->id,
        'sum' => $order->basket->items->sum('cost'),
        'refund_sum' => 0,
        'payed_at' => $faker->dateTimeInInterval('-1 days', '+5 days'),
        'expires_at' => $faker->dateTimeInInterval('+1 days', '+5 days'),
        'yandex_expires_at' => $faker->dateTimeInInterval('+1 days', '+5 days'),
        'status' => $faker->randomElement(PaymentStatus::validValues()),
        'payment_method' => $faker->randomElement(PaymentMethod::validValues()),
        'payment_system' => PaymentSystem::TEST,
        'data' => [
            'externalPaymentId' => $faker->uuid,
        ],
    ];
});
