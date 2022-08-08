<?php

namespace App\Services;

use App\Models\Order\OrderReturn;
use Pim\Services\CertificateService\CertificateService;

/**
 * Класс для формирования возвратов
 *
 * @package App\Services
 */
class RefundCertificateService
{
    private CertificateService $certificateService;

    public function __construct()
    {
        $this->certificateService = resolve(CertificateService::class);
    }

    public function refundSumToCertificate(OrderReturn $orderReturn): float
    {
        $order = $orderReturn->order;
        $returnPrepayment = $this->getPrepaymentSum($orderReturn);

        if ($returnPrepayment > 0) {
            try {
                $this->certificateService->rollback($returnPrepayment, $order->customer_id, $order->id, $order->number);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $returnPrepayment;
    }

    /**
     * В случае частичного возврата сначала возвращается сумма доплаты, потом сумма ПС
     */
    private function getPrepaymentSum(OrderReturn $orderReturn): float
    {
        $order = $orderReturn->order;
        $priceToReturn = $orderReturn->price;

        $remainingCashlessPrice = max(0, $order->cashless_price - $order->done_return_sum);

        $returnPrepayment = max(0, $priceToReturn - $remainingCashlessPrice);

        if (!$returnPrepayment) {
            return 0;
        }

        $returnedPrepayment = max(0, $order->done_return_sum - $order->cashless_price);
        $remainingPrepaymentPrice = max(0, $order->spent_certificate - $returnedPrepayment);

        return min($remainingPrepaymentPrice, $returnPrepayment);
    }
}
