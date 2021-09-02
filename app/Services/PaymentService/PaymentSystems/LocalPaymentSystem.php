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
    public const STATUS_DONE = 'done';

    /**
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

    public function paymentLink(Payment $payment): ?string
    {
        return $payment->data['paymentLink'] ?? null;
    }

    /**
     * @param array $data
     */
    public function handlePushPayment(array $data): void
    {
        $validator = Validator::make($data, [
            'paymentId' => 'required',
            'status' => 'required',
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
        if ($status == self::STATUS_DONE) {
            $payment->status = PaymentStatus::PAID;
            $payment->payed_at = Carbon::now();
            $payment->save();
        }
    }

    public function duration(): ?int
    {
        return 1;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function commitHoldedPayment(Payment $localPayment, $amount)
    {
        // TODO: Implement commitHoldedPayment() method.
    }

    public function externalPaymentId(Payment $payment): ?string
    {
        return $payment->data['paymentId'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function refund(string $paymentId, int $amount): array
    {
        return [
            'paymentId' => $paymentId,
            'amount' => $amount,
            'status' => self::STATUS_REFUND_SUCCESS,
        ];
    }

    /**
     * @inheritDoc
     */
    public function cancel(string $paymentId): array
    {
        return [
            'status' => self::STATUS_CANCELLED
        ];
    }
}
