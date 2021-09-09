<?php

namespace Tests\Unit;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnReason;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Faker\Factory;

class CreateOrderReturnTest extends TestCase
{
    use RefreshDatabase;

    public function testCancelOrder()
    {
        Event::fake();

        $faker = Factory::create('ru_RU');

        $orderReturnReasons = factory(OrderReturnReason::class, 5)->create();

        $basket = factory(Basket::class)->create();
        $basketItems = factory(BasketItem::class, 2)->create([
            'basket_id' => $basket->id,
        ]);
        $basketItemsCost = $basketItems->sum('cost');
        $basketItemsPrice = $basketItems->sum('price');

        $deliveryCost = $faker->numberBetween(0, 500);
        $deliveryPrice = $faker->numberBetween(0, $deliveryCost / 2);
        $order = factory(Order::class)->create([
            'basket_id' => $basket->id,
            'delivery_cost' => $deliveryCost,
            'delivery_price' => $deliveryPrice,
            'cost' => $basketItemsCost + $deliveryCost,
            'price' => $basketItemsPrice + $deliveryPrice,
            'payment_status' => PaymentStatus::PAID,
        ]);

        factory(Payment::class)->create([
            'order_id' => $order->id,
            'sum' => $basketItemsCost + $deliveryCost,
            'status' => PaymentStatus::PAID,
        ]);

        $deliveryDt = $faker->randomFloat(0, 1, 7);
        $deliveryAt = $order->created_at->modify('+' . $deliveryDt . ' days')->setTime(0, 0);

        $delivery = factory(Delivery::class)->create([
            'order_id' => $order->id,
            'tariff_id' => 0,
            'number' => Delivery::makeNumber($order->number, 1),
            'cost' => $deliveryCost,
            'dt' => $deliveryDt,
            'delivery_at' => $deliveryAt,
            'pdd' => $deliveryAt,
        ]);

        $shipment = factory(Shipment::class)->create([
            'delivery_id' => $delivery->id,
            'merchant_id' => $faker->randomNumber(),
            'psd' => $delivery->created_at->modify('+' . $faker->randomFloat(0, 120, 300) . ' minutes'),
            'store_id' => 1,
            'number' => Shipment::makeNumber((int) $delivery->order->number, 1, 1),
            'created_at' => $delivery->delivery_at->modify('+' . random_int(1, 7) . ' minutes'),
            'required_shipping_at' => $delivery->delivery_at->modify('+3 hours'),
            'status' => ShipmentStatus::CREATED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        foreach ($basketItems as $basketItem) {
            factory(ShipmentItem::class)->create([
                'shipment_id' => $shipment->id,
                'basket_item_id' => $basketItem->id,
            ]);
        }

        $randomReason = $orderReturnReasons->random();
        $response = $this->putJson("api/v1/deliveries/{$delivery->id}/cancel", [
            'orderReturnReason' => $randomReason->id,
        ]);
        $response->assertStatus(204);

        $existDelivery = Delivery::find($delivery->id)->first();
        $this->assertEquals($randomReason->id, $existDelivery->return_reason_id);
        $this->assertEquals(1, $existDelivery->is_canceled);

        $randomReason = $orderReturnReasons->random();
        $response = $this->putJson("api/v1/shipments/{$shipment->id}/cancel", [
            'orderReturnReason' => $randomReason->id,
        ]);
        $response->assertStatus(204);

        $existShipment = Shipment::find($shipment->id)->first();
        $this->assertEquals($randomReason->id, $existShipment->return_reason_id);
        $this->assertEquals(1, $existShipment->is_canceled);
        $orderReturn = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('is_delivery', false)
            ->first();
        $this->assertEquals($orderReturn->price, $basketItemsPrice);

        $randomReason = $orderReturnReasons->random();
        $response = $this->putJson("api/v1/orders/{$order->id}/cancel", [
            'orderReturnReason' => $randomReason->id,
        ]);
        $response->assertStatus(204);

        $orderReturn = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('is_delivery', true)
            ->first();
        $this->assertEquals($orderReturn->price, $deliveryPrice);
    }
}
