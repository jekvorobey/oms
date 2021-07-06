<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;

/**
 * Команда для получения url оплаты
 */
class GetPayUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:getpayurl {orderId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда для получения url оплаты';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(PaymentService $paymentService)
    {
        $orderId = $this->argument('orderId');
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('payments')->first();
        if (!$order) {
            throw new \Exception("Заказ с id=$orderId не найден");
        }

        dump($paymentService->start($order->payments->first()->id, 'random'));
    }
}
