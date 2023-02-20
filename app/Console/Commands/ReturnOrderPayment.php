<?php

namespace App\Console\Commands;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Services\RefundCertificateService;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use Illuminate\Console\Command;

class ReturnOrderPayment extends Command
{
    protected $signature = 'order:return';

    protected $description = 'Команда для возврата средств';

    public function handle()
    {
        $orderReturns = OrderReturn::query()->where('status', OrderReturn::STATUS_CREATED)->with('order.payments')->get();

        /** @var OrderReturn $orderReturn */
        foreach ($orderReturns as $orderReturn) {
            try {
                $this->refundPayment($orderReturn);
                $this->refundToCertificate($orderReturn);

                if ($orderReturn->status === OrderReturn::STATUS_CREATED) {
                    throw new \Exception(
                        "Не удалось обработать возврат #{$orderReturn->id} по заказу #{$orderReturn->order->id}"
                    );
                }

                if ($orderReturn->status !== OrderReturn::STATUS_FAILED) {
                    $order = $orderReturn->order;
                    $order->done_return_sum += $orderReturn->price;
                    $order->save();
                }

                $orderReturn->save();
            } catch (\Throwable $e) {
                report($e);
                $orderReturn->status = OrderReturn::STATUS_FAILED;
                $orderReturn->save();
            }
        }
    }

    private function refundPayment(OrderReturn $orderReturn): void
    {
        if (!in_array($orderReturn->order->payment_method_id, [PaymentMethod::PREPAID, PaymentMethod::B2B_SBERBANK], true)) {
            $orderReturn->status = OrderReturn::STATUS_DONE;
            return;
        }

        /** @var Payment $payment */
        $payment = $orderReturn->order->payments->last();
        if (!$payment) {
            return;
        }
        $paymentSystem = $payment->paymentSystem();
        if (!$paymentSystem) {
            return;
        }

        if ($payment->status === PaymentStatus::PAID && $orderReturn->price > 0) {
            $refundResponse = $paymentSystem->refund($payment, $orderReturn);

            $orderReturn->status =
                $refundResponse && $refundResponse['status'] === PaymentSystemInterface::STATUS_REFUND_SUCCESS
                    ? OrderReturn::STATUS_DONE
                    : OrderReturn::STATUS_FAILED;
        } else {
            $orderReturn->status = OrderReturn::STATUS_DONE;
        }
    }

    private function refundToCertificate(OrderReturn $orderReturn): void
    {
        if ($orderReturn->price > 0 && $orderReturn->order->spent_certificate > 0) {
            $certificateRefundService = new RefundCertificateService();
            $certificateRefundService->refundSumToCertificate($orderReturn);

            $orderReturn->status = OrderReturn::STATUS_DONE;
        }
    }
}
