<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Class OrderReturnUnpaidTickets
 * @package App\Console\Commands
 */
class OrderReturnUnpaidTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:return_unpaid_tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Вернуть билеты по неоплаченным заказам';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService)
    {
        $unpaidOrders = Order::query()
            ->where('type', Basket::TYPE_MASTER)
            ->where('payment_status', PaymentStatus::TIMEOUT)
            ->with('basket.items')
            ->get();
        $orderService->returnTickets($unpaidOrders);
    }
}
