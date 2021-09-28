<?php

namespace App\Console\Commands;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
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
            /** @var Payment $payment */
            $payment = $orderReturn->order->payments->last();

            if (!$payment) {
                continue;
            }

            $paymentId = $payment->data['externalPaymentId'];
            $paymentSystem = $payment->paymentSystem();

            if (!$paymentSystem) {
                continue;
            }

            if ($payment->status === PaymentStatus::PAID) {
                $refundResponse = $paymentSystem->refund($paymentId, $orderReturn);

                $orderReturn->status =
                    $refundResponse && $refundResponse['status'] === PaymentSystemInterface::STATUS_REFUND_SUCCESS
                        ? OrderReturn::STATUS_DONE
                        : OrderReturn::STATUS_FAILED;
            } else {
                $orderReturn->status = OrderReturn::STATUS_DONE;
            }

            $orderReturn->save();
        }
    }
}
