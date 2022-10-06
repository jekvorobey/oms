<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Services\PaymentService\PaymentSystems\KitInvest\Receipt\CreditReceiptData;
use App\Services\PaymentService\PaymentSystems\KitInvest\Receipt\RefundReceiptData;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use IBT\KitInvest\Enum\ReceiptEnum;
use IBT\KitInvest\KitInvest;
use IBT\KitInvest\Models\CheckModel;
use IBT\KitInvest\Models\ReceiptModel;
use IBT\KitInvest\Models\ResponseStatusModel;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Throwable;

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

    public function paymentInfo(Payment $payment): ?ResponseStatusModel
    {
        return $this->kitInvestService->getPaymentInfo($payment->external_payment_id);
    }

    public function createCreditPrepaymentReceipt(Payment $payment): ?array
    {
        $receiptData = new CreditReceiptData();
        $receiptData
            ->setPayAttribute(ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PREPAYMENT)
            ->setIsFullPayment(true);
        try {
            $request = $receiptData->getReceiptData($payment);
            $this->logger->info('Start create credit prepayment receipt', $request);
        } catch (Throwable $exception) {
            $this->logger->error('Error creating credit prepayment receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return $request ?? null;
    }

    public function createCreditReceipt(Payment $payment): ?array
    {
        $receiptData = new CreditReceiptData();
        $receiptData
            ->setPayAttribute(ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT)
            ->setIsFullPayment(true);

        try {
            $request = $receiptData->getReceiptData($payment);
            $this->logger->info('Start create credit receipt', $request);
        } catch (Throwable $exception) {
            $this->logger->error('Error creating credit receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return $request ?? null;
    }

    public function createCreditPaymentReceipt(Payment $payment): ?array
    {
        $receiptData = new CreditReceiptData();
        $receiptData
            ->setPayAttribute(ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT_PAYMENT)
            ->setIsFullPayment(true);

        try {
            $request = $receiptData->getReceiptData($payment);
            $this->logger->info('Start create credit payment receipt', $request);
        } catch (Throwable $exception) {
            $this->logger->error('Error creating credit payment receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return $request ?? null;
    }

    public function createReturnReceipt(Payment $payment, int $payAttribute, ?bool $isMerchant = true): ?array
    {
        $receiptData = new RefundReceiptData();
        $receiptData
            ->setPayAttribute($payAttribute)
            ->setIsFullPayment(true);

        try {
            $request = $receiptData->getReceiptData($payment, $isMerchant);
            $this->logger->info('Start create return payment receipt', $request);
        } catch (Throwable $exception) {

            dump($exception);

            $this->logger->error('Error creating return payment receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return $request ?? null;
    }

    public function sendReceipt(Payment $payment, array $receipt): ?array
    {
        $request = $this->kitInvestService->createRequest(true, true, true);
        $receiptModel = new ReceiptModel();
        $receiptModel
            ->setRequest($request)
            ->setCheck(new CheckModel($receipt));

        //ToDo Block send to service
        if ($receiptModel instanceof ReceiptModel) {
            return $receiptModel->toArray();
        }

        try {
            $result = $this->kitInvestService->sendReceiptModel($receiptModel);
            $this->logger->info('Send receipt', $receiptModel->toArray());
        } catch (Throwable $exception) {
            $this->logger->error('Error sending receipt', ['local_payment_id' => $payment->id, 'error' => $exception->getMessage()]);
            report($exception);
        }

        return isset($result) ? $result->toArray() : null;
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createIncomeReceipt(Payment $payment, bool $isFullPayment): void
    {
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function updatePaymentStatus(Payment $localPayment, $payment): void
    {
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function commitHoldedPayment(Payment $localPayment, $amount): void
    {
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function handlePushPayment(array $data): void
    {
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
    public function createRefundAllReceipt(Payment $payment): void
    {
        // TODO: Implement createIncomeReceipt() method.
    }

    /**
     * @inheritDoc
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function cancel(string $paymentId): array
    {
        return [];
    }
}
