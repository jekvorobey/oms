<?php

namespace Database\Factories;

use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Order\Order;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;

class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        /** @var Order $order */
        $order = Order::factory()->create([
            'number' => $this->faker->randomNumber(),
        ]);
        $deliveryDt = $this->faker->randomFloat(0, 1, 7);
        $deliveryAt = $order->created_at->addDays($deliveryDt)->setTime(0, 0);

        return [
            'order_id' => $order->id,
            'delivery_method' => $this->faker->randomElement(array_keys(DeliveryMethod::allMethods())),
            'delivery_service' => $this->faker->randomElement(array_keys(LogisticsDeliveryService::allServices())),
            'status' => DeliveryStatus::CREATED,
            'tariff_id' => 0,
            'number' => Delivery::makeNumber($order->number, 1),
            'cost' => $this->faker->randomFloat(2, 0, 500),
            'dt' => $deliveryDt,
            'delivery_at' => $deliveryAt,
            'pdd' => $deliveryAt,
        ];
    }
}
