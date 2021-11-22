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
    /** @var CertificateService */
    private $certificateService;

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

    public function getPrepaymentSum(OrderReturn $orderReturn): float
    {
        $order = $orderReturn->order;
        $priceToReturn = $orderReturn->price;

        $returnedPrepayment = max(0, $order->done_return_sum - $order->cashless_price);
        $remainingPrepaymentPrice = max(0, $order->spent_certificate - $returnedPrepayment);

        return $remainingPrepaymentPrice >= $priceToReturn ? $priceToReturn : $remainingPrepaymentPrice;
    }
}
