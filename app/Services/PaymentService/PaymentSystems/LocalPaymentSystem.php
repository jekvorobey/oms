<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class LocalPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class LocalPaymentSystem implements PaymentSystemInterface
{
    /** @var string */
    public const STATUS_DONE = 'done';

    /**
     * @param  Payment  $payment
     * @param  string  $returnLink
     * @throws Exception
     */
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

    /**
     * @param  Payment  $payment
     * @return string
     */
    public function paymentLink(Payment $payment): string
    {
        return $payment->data['paymentLink'];
    }

    /**
     * @param  array  $data
     */
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
            $payment->status = PaymentStatus::PAID;
            $payment->payed_at = Carbon::now();
            $payment->save();
        }
    }

    /**
     * @return int|null
     */
    public function duration(): ?int
    {
        return 1;
    }
}