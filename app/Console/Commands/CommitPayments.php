<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
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
        foreach ($payments as $payment) {
            if ($threeDaysAgo->greaterThan($payment->created_at)) {
                logger()->info('Commit holded payment', ['paymentId' => $payment->id]);
                try {
                    $payment->commitHolded();
                } catch (\Throwable $e) {
                    $payment->status = PaymentStatus::ERROR;
                    $payment->save();
                    logger()->error('unable to commit payment', ['exception' => $e]);
                }
            }
        }
    }
}
