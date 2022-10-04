<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Services\TicketNotifierService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Команда для тестирования отправки билетов заказа с мастер-классами по почте
 * Class OrderSendTicketsByEmail
 * @package App\Console\Commands
 */
class OrderSendTicketsByEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:send_tickets_by_email {orderId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправить билеты заказа с мастер-классами по почте';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle(): void
    {
        $orderId = $this->argument('orderId');
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('basket.items')->first();
        if (!$order) {
            throw new RuntimeException("Заказ с id=$orderId не найден");
        }


        if ($order->type == Basket::TYPE_MASTER) {
            app(TicketNotifierService::class)->notify($order);
        }
    }
}
