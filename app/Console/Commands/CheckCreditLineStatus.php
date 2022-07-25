<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Services\CreditService\CreditService;
use Illuminate\Console\Command;

class CheckCreditLineStatus extends Command
{
    protected $signature = 'creditline:check';
    protected $description = 'Проверить статусы кредитных договоров по кредитным заказам и актуализация статуса заказа';

    public function handle()
    {
        Order::query()
            ->where('type', Basket::TYPE_MASTER)
            ->each(function (Order $order) {
                $this->checkStatus($order);
            });
    }

    private function checkStatus(Order $order): void
    {
        try {
            $creditService = new CreditService();
            $creditService->checkStatus($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
