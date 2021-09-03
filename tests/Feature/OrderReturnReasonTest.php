<?php

namespace Tests\Feature;

use App\Models\Basket\Basket;
use App\Models\Order\OrderReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderReturnReasonTest extends TestCase
{
    use RefreshDatabase;

    public function testSuccessCancelOrderWithReturnReason(): void
    {
        Event::fake();
        $response = $this->putJson('api/v1/orders/return-reasons', [
            'text' => 'First reason to return',
        ]);
        $response->assertStatus(204);

    }

    public function testFailedCancelOrderWithReturnReason(): void
    {
        Event::fake();
    }
}
