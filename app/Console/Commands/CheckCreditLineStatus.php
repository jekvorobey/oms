<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Services\CreditService\CreditService;
use Illuminate\Console\Command;

class CheckCreditLineStatus extends Command
{
    protected $signature = 'creditline:check';
    protected $description = 'Проверить статусы кредитных договоров по кредитным заказам и актуализация статуса заказа';

    public function handle()
    {
        Order::query()
            ->where('payment_method_id', PaymentMethod::CREDITPAID)
            //->where('payment_status', PaymentStatus::NOT_PAID)
            ->each(function (Order $order) {
                $this->checkStatus($order);
            });
    }

    private function checkStatus(Order $order): void
    {
        try {
            $creditService = new CreditService();
            $checkStatus = $creditService->checkStatus($order);
        } catch (\Throwable $e) {
            report($e);
        }


        dump([$order->number, $checkStatus]);
    }
}
