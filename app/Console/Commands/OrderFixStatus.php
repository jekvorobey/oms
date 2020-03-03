<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Разовая команда!
 * Class OrderFixStatus
 * @package App\Console\Commands
 */
class OrderFixStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:fix_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Использование новых статусов у заказов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Order[] $orders */
        $orders = Order::query()->get();
        foreach ($orders as $order) {
            if ($order->status == 4) {
                /*
                 * Ниже специально не используется метод \App\Services\OrderService::cancel(),
                 * чтобы установить нужную дату и время отмены заказа
                 */
                $order->is_canceled = true;
                $order->is_canceled_at = $order->status_at;
            }

            $orderStatuses = OrderStatus::validValues();
            $order->setStatus($orderStatuses[array_rand($orderStatuses)]);

            $order->save();
        }
    }
}
