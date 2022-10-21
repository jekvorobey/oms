<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\PaymentSystems\KitInvest\KitInvestPaymentSystem;
use App\Services\PaymentService\PaymentSystems\Yandex\YandexPaymentSystem;
use IBT\KitInvest\Enum\ReceiptEnum;
use Illuminate\Console\Command;
use YooKassa\Model\Payment as YooKassaPayment;
use Throwable;

class ReturnIncomePrePaymentReceipt extends Command
{
    protected $signature = 'payment:return:prepayment {orderId?}';
    protected $description = 'Формирование возвратных фискальных чеков (предоплата) напрямую в Kit-invest';

    public function handle(OrderService $orderService, PaymentService $paymentService)
    {
        $orderId = $this->argument('orderId');
        $order = $orderService->getOrder($orderId);

        echo 'OrderId: ' . $order->id . "\n";
        foreach ($order->payments as $payment) {
            echo 'PaymentId: ' . $payment->id . "\n";
            if ($payment->payment_system === PaymentSystem::YANDEX) {
                $this->returnReceipt($payment, $paymentService);
            } else {
                echo 'Exit. PaymentSystemId: ' . $payment->payment_system . "\n";
            }
        }
    }

    private function returnReceipt(Payment $payment, PaymentService $paymentService): void
    {
        try {
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem instanceof YandexPaymentSystem) {
                echo 'ExternalPaymentId: ' . $payment->external_payment_id . "\n";
                $paymentInfo = $paymentSystem->paymentInfo($payment);
                echo 'PaymentInfoId: ' . $paymentInfo->getId() . "\n";
                if ($paymentInfo instanceof YooKassaPayment) {
                    $kitInvestPaymentSystem = new KitInvestPaymentSystem();
                    $paymentReceipt = $kitInvestPaymentSystem->createReturnReceipt($payment, ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PREPAYMENT, true);
                    if ($paymentReceipt) {
                        $kitInvestPaymentSystem->sendReceipt($payment, $paymentReceipt);
                    }
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
