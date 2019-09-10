<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    protected $signature = 'order:cancel_expired';
    
    protected $description = 'Отменить заказы у которых истёк срок оплаты неоплаченных оплат';
    
    public function handle()
    {
        $payments = Payment::expiredPayments();
        foreach ($payments as $payment) {
            $payment->order->cancel();
        }
    }
}
