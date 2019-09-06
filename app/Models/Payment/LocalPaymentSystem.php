<?php

namespace App\Models\Payment;

use Ramsey\Uuid\Uuid;

class LocalPaymentSystem
{
    /** @var Payment */
    private $payment;
    
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
    
    public function createExternalPayment(string $returnLink)
    {
        // тут мы обращаемся к системе оплаты
        // которая отдаёт нам всякие ID оплаты и ссылку
        // мы записываем всё в data
        $uuid = Uuid::uuid1()->toString();
        $data = $this->payment->data;
        $data['paymentId'] = $uuid;
        $data['returnLink'] = $returnLink;
        $data['handlerUrl'] = route('handler.localPayment');
        $data['paymentLink'] = route('paymentPage', ['paymentId' => $uuid]);
        $this->payment->data = $data;
        $this->payment->save();
    }
    
    public function paymentLink()
    {
        return $this->payment->data['paymentLink'];
    }
}
