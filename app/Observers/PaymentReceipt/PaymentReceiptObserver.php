<?php

namespace App\Observers\PaymentReceipt;

use App\Models\Payment\PaymentReceipt;
use Illuminate\Support\Str;

class PaymentReceiptObserver
{
    public function creating(PaymentReceipt $paymentReceipt): void
    {
        $paymentReceipt->guid = (string) Str::uuid();
    }

    public function saving(PaymentReceipt $paymentReceipt): void
    {
        if ($paymentReceipt->isDirty('status')) {
            $paymentReceipt->payed_at = now();
        }

        if (!$paymentReceipt->guid) {
            $paymentReceipt->guid = (string) Str::uuid();
        }
    }

    public function saved(PaymentReceipt $paymentReceipt): void
    {
    }
}
