<?php

namespace App\Console\Commands\OneTime;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class FixPublicEventOrdersStatus
 * @package App\Console\Commands\OneTime
 */
class FixPublicEventOrdersStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:fix_public_event_orders_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Поправить некорректно установленные статусы для заказов с мастер-классами';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Order[] $orders */
        $orders = Order::query()
            ->where('type', Basket::TYPE_MASTER)
            ->where(function(Builder $query) {
                $query->where('status', OrderStatus::AWAITING_CONFIRMATION)
                    ->orWhereHas('orderReturns');
            })
            ->with('orderReturns')
            ->get();
        foreach ($orders as $order) {
            $order->status = $order->orderReturns->count() ? OrderStatus::RETURNED : OrderStatus::DONE;
            $order->save();
        }
    }
}
