<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;
use Throwable;

class CheckRaiffeisenPayments extends Command
{
    protected $signature = 'payments:raiffeisen:check';
    protected $description = 'Проверить платежи Raiffeisen на наличие и актуализировать статус';

    public function handle(): void
    {
        Payment::query()
            ->where('payment_system', PaymentSystem::RAIFFEISEN)
            /*
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->whereNotIn('status', [PaymentStatus::PAID, PaymentStatus::TIMEOUT, PaymentStatus::ERROR])
                        ->where('created_at', '<', now()->subHour());
                })->orWhere(function ($query) {
                    $query
                        ->whereIn('status', [PaymentStatus::ERROR])
                        ->where('created_at', '>', now()->subDays(7));
                });
            })
            */
            ->with('order')
            ->each(function (Payment $payment) {
                if ($payment->order->remaining_price <= $payment->order->spent_certificate) {
                    return;
                }

                $this->checkStatus($payment);
            });
    }

    private function checkStatus(Payment $payment): void
    {
        dump($payment->external_payment_id);
        try {
            $paymentService = new PaymentService();
            $paymentService->updatePaymentInfo($payment);
        } catch (Throwable $e) {
            $payment->status = PaymentStatus::ERROR;
            $payment->save();
            report($e);
        }
    }
}
