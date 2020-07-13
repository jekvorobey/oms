<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

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
     * @throws \Exception
     */
    public function handle(OrderService $orderService)
    {
        $orderId = $this->argument('orderId');
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('basket.items')->first();
        if (!$order) {
            throw new \Exception("Заказ с id=$orderId не найден");
        }

        $orderService->sendTicketsEmail($order);
    }
}
