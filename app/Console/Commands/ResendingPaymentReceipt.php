<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentSystems\Yandex\YandexPaymentSystem;
use Illuminate\Console\Command;
use YooKassa\Model\Payment as YooKassaPayment;
use Throwable;

class ResendingPaymentReceipt extends Command
{
    protected $signature = 'payment:resending {orderId?}';
    protected $description = 'Повторная отправка оплат для формирования фискальных чеков';

    public function handle(OrderService $orderService)
    {
        $orderId = $this->argument('orderId');
        $order = $orderService->getOrder($orderId);

        echo 'OrderId: ' . $order->id . "\n";
        foreach ($order->payments as $payment) {
            echo 'PaymentId: ' . $payment->id . "\n";
            if ($payment->payment_system === PaymentSystem::YANDEX) {
                $this->resendingReceipt($payment);
            } else {
                echo 'Exit. PaymentSystemId: ' . $payment->payment_system . "\n";
            }
        }
    }

    private function resendingReceipt(Payment $payment): void
    {
        try {
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem instanceof YandexPaymentSystem) {
                echo 'ExternalPaymentId: ' . $payment->external_payment_id . "\n";
                $paymentInfo = $paymentSystem->paymentInfo($payment);
                echo 'PaymentInfoId: ' . $paymentInfo->getId() . "\n";
                if ($paymentInfo instanceof YooKassaPayment) {
                    $paymentSystem->handlePushPayment($paymentInfo->toArray());
                }
            }
        } catch (Throwable $e) {
            echo 'Error: ' . $e->getMessage() . "\n\n";
            report($e);

            return;
        }

        echo 'Success' . "\n\n";
    }
}
