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
    public function testSuccessOrderReturnReasonCrud($orderReturnReason): void
    {
        Event::fake();
        $response = $this->postJson('api/v1/orders/return-reasons', [
            'text' => $orderReturnReason,
        ]);
        $response->assertStatus(201);
        $savedOrderReturnReason = OrderReturnReason::all()->first();
        $this->assertEquals($savedOrderReturnReason->text, $orderReturnReason);

        $response = $this->putJson('api/v1/orders/return-reasons/' . $savedOrderReturnReason->id, [
            'text' => $orderReturnReason . ' updated',
        ]);
        $response->assertStatus(204);

        $response = $this->get('api/v1/orders/return-reasons/' . $savedOrderReturnReason->id);
        $response->assertJsonFragment([
            'text' => $orderReturnReason . ' updated',
        ]);

        $response = $this->delete('api/v1/orders/return-reasons/' . $savedOrderReturnReason->id);
        $response->assertStatus(204);

        $deletedOrderReturnReason = OrderReturnReason::find($savedOrderReturnReason->id);
        $this->assertNull($deletedOrderReturnReason);
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
