<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        /** @var Order $order */
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'sum' => $order->basket->items->sum('cost'),
            'refund_sum' => 0,
            'payed_at' => $this->faker->dateTimeInInterval('-1 days', '+5 days'),
            'expires_at' => $this->faker->dateTimeInInterval('+1 days', '+5 days'),
            'yandex_expires_at' => $this->faker->dateTimeInInterval('+1 days', '+5 days'),
            'status' => $this->faker->randomElement(PaymentStatus::validValues()),
            'payment_method' => $this->faker->randomElement(PaymentMethod::validValues()),
            'payment_system' => PaymentSystem::TEST,
            'data' => [
                'externalPaymentId' => $this->faker->uuid,
            ],
        ];
    }
}
