<?php

namespace Database\Factories;

use App\Models\Basket\Basket;
use Illuminate\Database\Eloquent\Factories\Factory;

class BasketFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'customer_id' => $this->faker->randomNumber(),
            'is_belongs_to_order' => true,
            'type' => Basket::TYPE_PRODUCT,
        ];
    }
}
