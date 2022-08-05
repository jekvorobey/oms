<?php

namespace Database\Factories;

use App\Models\Basket\Basket;
use Illuminate\Database\Eloquent\Factories\Factory;

class BasketItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $price = $this->faker->numberBetween(100, 5000);
        $qty = $this->faker->numberBetween(1, 6);
        $cost = $price * $qty;

        return [
            'basket_id' => function () {
                return Basket::factory()->create()->id;
            },
            'offer_id' => $this->faker->randomNumber(),
            'type' => Basket::TYPE_PRODUCT,
            'name' => $this->faker->title,
            'qty' => $qty,
            'price' => $price,
            'cost' => $cost,
            'product' => [
                'store_id' => $this->faker->numberBetween(1, 6),
                'weight' => $this->faker->numberBetween(1, 100),
                'width' => $this->faker->numberBetween(1, 100),
                'height' => $this->faker->numberBetween(1, 100),
                'length' => $this->faker->numberBetween(1, 100),
                'merchant_id' => $this->faker->numberBetween(1, 100),
                'is_danger' => $this->faker->boolean,
            ],
        ];
    }
}
