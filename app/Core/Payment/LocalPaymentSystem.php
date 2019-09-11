<?php

namespace App\Core\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalPaymentSystem implements PaymentSystemInterface
{
    public const STATUS_DONE = 'done';

    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $uuid = Uuid::uuid1()->toString();
        $data = $payment->data;
        $data['paymentId'] = $uuid;
        $data['returnLink'] = $returnLink;
        $data['handlerUrl'] = route('handler.localPayment');
        $data['paymentLink'] = route('paymentPage', ['paymentId' => $uuid]);
        $payment->data = $data;
        $payment->save();
    }

    public function paymentLink(Payment $payment): string
    {
        return $payment->data['paymentLink'];
    }

    public function handlePushPayment(array $data): void
    {
        $validator = Validator::make($data, [
            'paymentId' => 'required',
            'status' => 'required'
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        ['paymentId' => $paymentId, 'status' => $status] = $data;
        /** @var Payment $payment */
        $payment = Payment::query()
            ->where('data->paymentId', $paymentId)
            ->first();
        if (!$payment) {
            throw new NotFoundHttpException();
        }
        if (self::STATUS_DONE == $status) {
            $payment->status = PaymentStatus::DONE;
            $payment->payed_at = Carbon::now();
            $payment->save();
        }
    }
    
    public function duration(): ?int
    {
        return 1;
    }
}
