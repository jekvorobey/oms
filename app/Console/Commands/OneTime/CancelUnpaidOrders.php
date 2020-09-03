<?php

namespace App\Console\Commands\OneTime;

use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class CancelUnpaidOrders
 * @package App\Console\Commands\OneTime
 */
class CancelUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:cancel_unpaid_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отменить все неоплаченные заказы';

    /**
     * Execute the console command.
     * @param  OrderService  $orderService
     * @throws \Exception
     */
    public function handle(OrderService $orderService)
    {
        /** @var Collection|Order[] $orders */
        $orders = Order::query()->where('payment_status', PaymentStatus::TIMEOUT)->get();
        foreach ($orders as $order) {
            try {
                $orderService->cancel($order);
            } catch (\Exception $e) {
            }
        }
    }
}
