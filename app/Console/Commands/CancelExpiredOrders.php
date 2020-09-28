<?php

namespace App\Console\Commands;

use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;

/**
 * Class CancelExpiredOrders
 * @package App\Console\Commands
 */
class CancelExpiredOrders extends Command
{
    /** @var string */
    protected $signature = 'order:cancel_expired';
    /** @var string */
    protected $description = 'Отменить заказы, у которых истёк срок оплаты неоплаченных оплат';

    /**
     * @param  PaymentService  $paymentService
     */
    public function handle(PaymentService $paymentService)
    {
        $payments = $paymentService->expiredPayments();
        foreach ($payments as $payment) {
            $paymentService->timeout($payment);
        }
    }
}
