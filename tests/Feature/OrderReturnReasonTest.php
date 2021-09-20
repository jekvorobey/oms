<?php

namespace Tests\Feature;

use App\Models\Order\OrderReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderReturnReasonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider correctOrderReturnReasonProvider
     */
    public function createOrderReturn($orderReturnReason): void
    {
        $this->addOrderReturn($orderReturnReason);
        $this->assertDatabaseHas((new OrderReturnReason())->getTable(), ['text' => $orderReturnReason]);
    }

    /**
     * @dataProvider correctOrderReturnReasonProvider
     */
    public function deleteOrderReturn($orderReturnReason): void
    {
        Event::fake();
        $orderReturnReasonId = $this->addOrderReturn($orderReturnReason);
        $response = $this->delete('api/v1/orders/return-reasons/' . $orderReturnReasonId);
        $response->assertStatus(204);
        $this->assertDatabaseMissing((new OrderReturnReason())->getTable(), ['text' => $orderReturnReason]);
    }

    /**
     * @dataProvider correctOrderReturnReasonProvider
     */
    public function updateOrderReturn($orderReturnReason): void
    {
        Event::fake();
        $orderReturnReasonId = $this->addOrderReturn($orderReturnReason);
        $response = $this->putJson('api/v1/orders/return-reasons/' . $orderReturnReasonId, [
            'text' => $orderReturnReason . ' updated',
        ]);
        $response->assertStatus(204);
    }

    /**
     * @dataProvider correctOrderReturnReasonProvider
     */
    public function readOrderReturn($orderReturnReason): void
    {
        Event::fake();
        $orderReturnReasonId = $this->addOrderReturn($orderReturnReason);
        $response = $this->get('api/v1/orders/return-reasons/' . $orderReturnReasonId);
        $response->assertJsonFragment([
            'text' => $orderReturnReason . ' updated',
        ]);
    }

    public function addOrderReturn($orderReturnReason): int
    {
        Event::fake();
        $response = $this->postJson('api/v1/orders/return-reasons', [
            'text' => $orderReturnReason,
        ]);

        return $response->original['id'];
    }

    /**
     * @dataProvider inCorrectOrderReturnReasonProvider
     */
    public function testFailedOrderReturnReasonCrud($orderReturnReason): void
    {
        Event::fake();
        $response = $this->postJson('api/v1/orders/return-reasons', [
            'text' => $orderReturnReason,
        ]);
        $response->assertStatus(400);
    }

    public function correctOrderReturnReasonProvider()
    {
        return [['First reason'], ['Second reason'], ['Third reason'], ['Fourth reason']];
    }

    public function inCorrectOrderReturnReasonProvider()
    {
        return [[222], [false], [3.14]];
    }
}
