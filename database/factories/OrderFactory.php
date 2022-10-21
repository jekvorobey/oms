<?php

namespace Database\Factories;

use App\Models\Basket\Basket;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        /** @var Basket $basket */
        $basket = Basket::factory()->create();
        $basketItems = $basket->items;
        $basketItemsCost = $basketItems->sum('cost');
        $basketItemsPrice = $basketItems->sum('price');

        $deliveryCost = $this->faker->numberBetween(0, 500);
        $deliveryPrice = $this->faker->numberBetween(0, $deliveryCost / 2);
        $cost = $basketItemsCost + $deliveryCost;
        $price = $basketItemsPrice + $deliveryPrice;

        return [
            'basket_id' => $basket->id,
            'customer_id' => $this->faker->randomNumber(),
            'type' => Basket::TYPE_PRODUCT,
            'status' => $this->faker->randomElement(
                [
                    OrderStatus::CREATED,
                    OrderStatus::AWAITING_CHECK,
                    OrderStatus::CHECKING,
                    OrderStatus::AWAITING_CONFIRMATION,
                ]
            ),
            'created_at' => $this->faker->dateTimeThisYear(),
            'delivery_type' => $this->faker->randomElement(DeliveryType::validValues()),
            'delivery_cost' => $deliveryCost,
            'delivery_price' => $deliveryPrice,
            'cost' => $cost,
            'price' => $price,
            'spent_certificate' => 0,
            'is_require_check' => $this->faker->boolean(),
            'payment_status' => $this->faker->randomElement(PaymentStatus::validValues()),
        ];
    }
}
