<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Команда для тестирования оплаты заказа!
 */
class OrderPay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:pay {orderId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда для тестирования оплаты заказа';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle()
    {
        $orderId = $this->argument('orderId');
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('payments')->first();
        if (!$order) {
            throw new \Exception("Заказ с id=$orderId не найден");
        }

        /** @var Payment $payment */
        $payment = $order->payments->first();
        if (!$payment) {
            throw new \Exception("Оплата для заказа с id=$orderId не найдена");
        }
        $payment->status = PaymentStatus::PAID;
        $payment->payed_at = Carbon::now();
        $payment->save();
    }
}
