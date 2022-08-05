<?php

namespace Database\Factories;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Payment\PaymentStatus;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     * @throws Exception
     */
    public function definition(): array
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::factory()->create();
        /** @var Cargo $cargo */
        $cargo = Cargo::factory()->create();

        return [
            'status' => $this->faker->randomElement(ShipmentStatus::validValues()),
            'delivery_id' => $delivery->id,
            'merchant_id' => $this->faker->randomNumber(),
            'psd' => $delivery->created_at->modify('+' . $this->faker->randomFloat(0, 120, 300) . ' minutes'),
            'store_id' => 1,
            'number' => Shipment::makeNumber((int) $delivery->order->number, 1, 1),
            'created_at' => $delivery->delivery_at->modify('+' . random_int(1, 7) . ' minutes'),
            'required_shipping_at' => $delivery->delivery_at->modify('+3 hours'),
            'payment_status' => $this->faker->randomElement(PaymentStatus::validValues()),
            'cargo_id' => $cargo->id,
            'return_reason_id' => null,
        ];
    }
}
