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
            $this->certificateService->rollback($returnPrepayment, $order->customer_id, $order->id, $order->number);
        }

        return $returnPrepayment;
    }

    public function getPrepaymentSum(OrderReturn $orderReturn): float
    {
        $order = $orderReturn->order;
        $priceToReturn = $orderReturn->price;

        $restReturnPrice = max(0, $order->price - $order->done_return_sum);
        $restCashlessReturnPrice = max(0, $restReturnPrice - $order->spent_certificate);

        return max(0, $priceToReturn - $restCashlessReturnPrice);
    }
}
