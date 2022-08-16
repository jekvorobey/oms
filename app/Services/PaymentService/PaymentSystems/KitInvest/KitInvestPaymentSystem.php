<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\KitInvest\Receipt\IncomeReceiptData;
use IBT\KitInvest\KitInvest;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

class KitInvestPaymentSystem implements PaymentSystemInterface
{
    /** @var KitInvest */
    private $kitInvestService;
    /** @var Logger */
    private $logger;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->kitInvestService = resolve(KitInvest::class);
        $this->logger = Log::channel('payments-kit-invest');
    }

    public function paymentInfo(Payment $payment)
    {
        return $this->kitInvestService->getPaymentInfo($payment->external_payment_id);
    }

    public function createIncomeReceipt(Payment $payment, bool $isFullPayment): void
    {
        try {
            $receiptData = new IncomeReceiptData();
            $receiptData->setIsFullPayment($isFullPayment);
            $request = $receiptData->getReceiptData($payment->order, $payment->external_payment_id);
            $this->logger->info('Start create receipt', $request->toArray());

            $this->kitInvestService->createReceipt($request);
        } catch (\Throwable $exception) {
            $this->logger->error('Error creating receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }

    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        return;
    }

    public function createCreditReceipt(Payment $payment): void
    {
        return;
    }

    public function createCreditPaymentReceipt(Payment $payment): void
    {
        return;
    }

    public function updatePaymentStatus(Payment $localPayment, $payment): void
    {
        return;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function commitHoldedPayment(Payment $localPayment, $amount)
    {
        return;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function handlePushPayment(array $data): void
    {
        return;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function duration(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function refund(Payment $payment, OrderReturn $orderReturn): array
    {
        return [];
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function cancel(string $paymentId): array
    {
        return [];
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createRefundAllReceipt(Payment $payment): void
    {
        return;
    }
}
