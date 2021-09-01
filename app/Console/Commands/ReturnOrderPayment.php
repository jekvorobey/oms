<?php

namespace App\Console\Commands;

use App\Models\Order\OrderReturn;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use Illuminate\Console\Command;

class ReturnOrderPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:return';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда для возврата средств через ЮКасса';

    public function handle()
    {
        $orderReturns = OrderReturn::query()->where('status', OrderReturn::STATUS_CREATED)->with('order.payments')->get();

        foreach ($orderReturns as $orderReturn) {
            $payment = $orderReturn->order->payments->first();

            if ($payment) {
                $paymentId = $payment->data['externalPaymentId'];
                $paymentSystem = $payment->paymentSystem();

                if ($paymentSystem) {
                    $refundResponse = $paymentSystem->refund($paymentId, $orderReturn->price);

                    $orderReturn->status = $refundResponse && $refundResponse['status'] === PaymentSystemInterface::STATUS_REFUND_SUCCESS ? OrderReturn::STATUS_DONE : OrderReturn::STATUS_FAILED;
                    $orderReturn->save();
                }
            }
        }
    }
}
