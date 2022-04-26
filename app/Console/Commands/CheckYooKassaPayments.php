<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

class CheckYooKassaPayments extends Command
{
    protected $signature = 'payments:check';
    protected $description = 'Проверить платежи ЮКассы на наличие и актуализировать статус';

    public function handle()
    {
        Payment::query()
            ->whereNotIn('status', [PaymentStatus::PAID, PaymentStatus::TIMEOUT, PaymentStatus::ERROR])
            ->whereHas('order', function (Builder $query) {
                $query->whereColumn('remaining_price', '<=', 'spent_certificate');
            })
            ->where('payment_system', PaymentSystem::YANDEX)
            ->whereDate('created_at', '<', now()->subHour())
            ->each(function (Payment $payment) {
                $this->checkStatus($payment);
            });
    }

    private function checkStatus(Payment $payment): void
    {
        try {
            $paymentService = new PaymentService();
            $paymentService->updatePaymentInfo($payment);
        } catch (\Throwable $e) {
            $payment->status = PaymentStatus::ERROR;
            $payment->save();
            report($e);
        }
    }
}
