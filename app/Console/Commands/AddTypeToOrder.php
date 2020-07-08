<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class AddTypeToOrder
 * @package App\Console\Commands
 */
class AddTypeToOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:add_type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Заполнить type для заказов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Order[] $orders */
        $orders = Order::query()->with('basket')->get();
        foreach ($orders as $order) {
            $order->type = $order->basket->type;
            $order->save();
        }
    }
}
