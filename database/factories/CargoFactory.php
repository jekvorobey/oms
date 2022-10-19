<?php

namespace Database\Factories;

use App\Models\Delivery\CargoStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;

class CargoFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'merchant_id' => $this->faker->randomNumber(),
            'store_id' => 1,
            'status' => $this->faker->randomElement(CargoStatus::validValues()),
            'delivery_service' => $this->faker->randomElement(array_keys(LogisticsDeliveryService::allServices())),
            'xml_id' => $this->faker->uuid,
            'width' => $this->faker->randomFloat(3, 1, 6),
            'height' => $this->faker->randomFloat(3, 1, 6),
            'length' => $this->faker->randomFloat(3, 1, 6),
            'weight' => $this->faker->randomFloat(3, 1, 6),
        ];
    }
}
