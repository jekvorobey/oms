<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
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
    public const CURRENCY_RUB = 'RUB';

    /**
     * @throws Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $data = $payment->data;
        $data['returnLink'] = $returnLink;
        $data['handlerUrl'] = route('handler.localPayment');
        $payment->data = $data;

        $uuid = Uuid::uuid1()->toString();
        $payment->external_payment_id = $uuid;
        $payment->payment_link = route('paymentPage', ['paymentId' => $uuid]);
        $payment->save();
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

    /**
     * @inheritDoc
     */
    public function refund(Payment $payment, OrderReturn $orderReturn): array
    {
        $items = [];
        if ($orderReturn->is_delivery) {
            $items[] = [
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => number_format($orderReturn->price, 2, '.', ''),
                    'currency' => self::CURRENCY_RUB,
                ],
                'vat_code' => 1,
            ];
        } else {
            foreach ($orderReturn->items as $item) {
                $itemValue = $item->price / $item->qty;

                $items[] = [
                    'description' => $item->name,
                    'quantity' => $item->qty,
                    'amount' => [
                        'value' => number_format($itemValue, 2, '.', ''),
                        'currency' => self::CURRENCY_RUB,
                    ],
                    'vat_code' => 1,
                ];
            }
        }
        $captureData = [
            'amount' => [
                'value' => $orderReturn->price,
                'currency' => self::CURRENCY_RUB,
            ],
            'payment_id' => $payment->external_payment_id,
            'receipt' => [
                'tax_system_code' => '2',
                'phone' => $orderReturn->order->customerPhone(),
                'items' => $items,
            ],
        ];

        return [
            'paymentId' => $payment->external_payment_id,
            'amount' => $orderReturn->price,
            'status' => self::STATUS_REFUND_SUCCESS,
            'receipt' => $captureData['receipt'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function cancel(string $paymentId): array
    {
        return [
            'paymentId' => $paymentId,
            'status' => self::STATUS_CANCELLED,
        ];
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createIncomeReceipt(Payment $payment, bool $isFullPayment): void
    {
        // TODO: Implement createIncomeReceipt() method.
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditPrepaymentReceipt(Payment $payment): ?array
    {
        // TODO: Implement createCreditPrepaymentReceipt() method.
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditReceipt(Payment $payment): ?array
    {
        // TODO: Implement createCreditReceipt() method.
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createCreditPaymentReceipt(Payment $payment): ?array
    {
        // TODO: Implement createCreditPaymentReceipt() method.
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createReturnReceipt(Payment $payment, int $payAttribute, ?bool $isMerchant = true): ?array
    {
        // TODO: Implement createReturnReceipt() method.
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function sendReceipt(Payment $payment, array $receipt, int $type): ?array
    {
        // TODO: Implement createCreditPaymentReceipt() method.
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createRefundAllReceipt(Payment $payment): void
    {
        // TODO: Implement createIncomeReceipt() method.
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function paymentInfo(Payment $payment)
    {
        // TODO: Implement paymentInfo() method.
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function updatePaymentStatus(Payment $localPayment, $payment): void
    {
        // TODO: Implement updatePaymentStatus() method.
    }
}
