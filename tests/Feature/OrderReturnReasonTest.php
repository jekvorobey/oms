<?php

namespace Tests\Feature;

use App\Models\Order\OrderReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderReturnReasonTest extends TestCase
{
    use RefreshDatabase;

    public function createOrderReturnReasons(): Collection
    {
        $orderReturnReasons = factory(OrderReturnReason::class, 3)->create();

        return collect($orderReturnReasons);
    }

    public function testSuccessCancelOrderWithReturnReason(): void
    {
        $orderReturnReasons = $this->createOrderReturnReasons();
    }

    public function testFailedCancelOrderWithReturnReason(): void
    {

    }
}
