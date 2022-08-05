<?php

namespace Database\Factories;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'shipment_id' => function () {
                return Shipment::factory()->create()->id;
            },
            'basket_item_id' => function () {
                return BasketItem::factory()->create()->id;
            },
            'created_at' => $this->faker->dateTime,
        ];
    }
}
