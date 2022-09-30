<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\PaymentSystems\Yandex\YandexPaymentSystem;
use Illuminate\Console\Command;
use YooKassa\Model\Payment as YooKassaPayment;
use Throwable;

class ResendingIncomePrePaymentReceipt extends Command
{
    protected $signature = 'payment:resending:prepayment {orderId?}';
    protected $description = 'Повторная отправка оплат формирования фискальных чеков (предоплата)';

    public function handle(OrderService $orderService, PaymentService $paymentService)
    {
        $orderId = $this->argument('orderId');
        $order = $orderService->getOrder($orderId);

        echo 'OrderId: ' . $order->id . "\n";
        foreach ($order->payments as $payment) {
            echo 'PaymentId: ' . $payment->id . "\n";
            if ($payment->payment_system === PaymentSystem::YANDEX) {
                $this->resendingReceipt($payment, $paymentService);
            } else {
                echo 'Exit. PaymentSystemId: ' . $payment->payment_system . "\n";
            }
        }
    }

    private function resendingReceipt(Payment $payment, PaymentService $paymentService): void
    {
        try {
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem instanceof YandexPaymentSystem) {
                echo 'ExternalPaymentId: ' . $payment->external_payment_id . "\n";
                $paymentInfo = $paymentSystem->paymentInfo($payment);
                echo 'PaymentInfoId: ' . $paymentInfo->getId() . "\n";
                if ($paymentInfo instanceof YooKassaPayment) {
                    $payment->is_prepayment_receipt_sent = false;
                    $payment->save();

                    $paymentService->sendIncomePrepaymentReceipt($payment);
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
