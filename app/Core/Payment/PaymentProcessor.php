<?php

namespace App\Core\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;

class PaymentProcessor
{
    public function paymentById(int $id): ?Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()->where('id', $id)->first();
        return $payment;
    }
    public function startPayment(Payment $payment, string $returnUrl)
    {
        $payment->status = PaymentStatus::STARTED;
        $payment->save();
    
        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->createExternalPayment($returnUrl);
        
        return $paymentSystem->paymentLink();
    }
}
