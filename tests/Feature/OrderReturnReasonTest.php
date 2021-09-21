<?php

namespace Tests\Feature;

use App\Models\Order\OrderReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutEvents;
use Tests\TestCase;

class OrderReturnReasonTest extends TestCase
{
    use RefreshDatabase, WithoutEvents;

    /**
     * @dataProvider correctProvider
     */
    public function testCreate($orderReturnReason): void
    {
        $response = $this->postJson('api/v1/orders/return-reasons', [
            'text' => $orderReturnReason,
        ]);
        $this->assertDatabaseHas((new OrderReturnReason())->getTable(), ['text' => $orderReturnReason]);
        $response->assertJsonStructure(['id']);
    }

    public function testDelete(): void
    {
        $orderReturnReason = factory(OrderReturnReason::class)->create();
        $response = $this->delete('api/v1/orders/return-reasons/' . $orderReturnReason->id);
        $response->assertStatus(204);
        $this->assertDatabaseMissing((new OrderReturnReason())->getTable(), ['id' => $orderReturnReason->id]);
    }

    public function testUpdate(): void
    {
        $orderReturnReason = factory(OrderReturnReason::class)->create();
        $response = $this->putJson('api/v1/orders/return-reasons/' . $orderReturnReason->id, [
            'text' => $orderReturnReason->text . ' updated',
        ]);
        $response->assertStatus(204);
        $this->assertDatabaseHas((new OrderReturnReason())->getTable(), ['text' => $orderReturnReason->text . ' updated']);
    }

    public function testRead(): void
    {
        $orderReturnReason = factory(OrderReturnReason::class)->create();
        $response = $this->get('api/v1/orders/return-reasons/' . $orderReturnReason->id);
        $response->assertJsonFragment([
            'text' => $orderReturnReason->text,
        ]);
    }

    /**
     * @dataProvider inCorrectProvider
     */
    public function testFailedAdd($orderReturnReason): void
    {
        $response = $this->postJson('api/v1/orders/return-reasons', [
            'text' => $orderReturnReason,
        ]);
        $response->assertStatus(400);
    }

    public function correctProvider()
    {
        return [['First reason'], ['Second reason'], ['Third reason'], ['Fourth reason']];
    }

    public function inCorrectProvider()
    {
        return [[222], [false], [3.14]];
    }
}
