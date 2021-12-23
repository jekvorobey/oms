<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CommitPayments extends Command
{
    protected $signature = 'payments:commit';
    protected $description = 'Commit holded money when payment reach 3 day age';

    public function handle()
    {
        /** @var Collection|Payment[] $payments */
        $payments = Payment::query()
            ->where('status', PaymentStatus::HOLD)
            ->get();
        $threeDaysAgo = now()->subDays(3);
        $today = now();
        foreach ($payments as $payment) {
            if ($payment->yandex_expires_at) {
                if ($payment->yandex_expires_at->diff($today)->days < 1) {
                    $this->commitHolded($payment);
                }

                continue;
            }
            if ($threeDaysAgo->greaterThan($payment->created_at)) {
                $this->commitHolded($payment);
            }
        }
    }

    private function commitHolded(Payment $payment): void
    {
        logger()->info('Commit holded payment', ['paymentId' => $payment->id]);
        try {
            $paymentService = new PaymentService();
            $paymentService->capture();
        } catch (\Throwable $e) {
            $payment->status = PaymentStatus::ERROR;
            $payment->save();
            logger()->error('unable to commit payment', ['exception' => $e]);
        }
    }
}
