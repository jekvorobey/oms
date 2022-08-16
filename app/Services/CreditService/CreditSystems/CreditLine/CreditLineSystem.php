<?php

namespace App\Services\CreditService\CreditSystems\CreditLine;

use App\Services\CreditService\CreditSystems\CreditSystemInterface;
use IBT\CreditLine\CreditLine;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

/**
 * Class CreditLineSystem
 * @package App\Services\CreditService\CreditSystems\CreditLine
 */
class CreditLineSystem implements CreditSystemInterface
{
    public const CREDIT_ORDER_ERROR_NOT_FIND = -5;

    /** @var CreditLine */
    private $creditLineService;
    /** @var Logger */
    private $logger;

    /**
     * CreditLineSystem constructor.
     */
    public function __construct()
    {
        $this->creditLineService = resolve(CreditLine::class);
        $this->logger = Log::channel('credits');

        //$this->creditLineService->auth(env('CREDIT_LINE_PAYMENT_HOST'), env('CREDIT_LINE_PAYMENT_LOGIN'), env('CREDIT_LINE_PAYMENT_PASSWORD'));
    }

    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function checkCreditOrder(string $id): ?array
    {
        $creditOrder = $this->creditLineService->getOrderStatus($id);

        if ($creditOrder->getErrorCode() === self::CREDIT_ORDER_ERROR_NOT_FIND) {
            return null;
        }

        return [
            'status' => $creditOrder->getStatus(),
            'statusId' => $creditOrder->getStatusId(),
            'statusDescription' => $creditOrder->getStatusDescription(),
            'discount' => $creditOrder->getDiscount(),
            'numOrder' => $creditOrder->getNumOrder(),
            'confirm' => $creditOrder->getConfirm(),
            'initPay' => $creditOrder->getInitPay(),
            'bankCode' => $creditOrder->getBankCode(),
            'bankName' => $creditOrder->getBankName(),
        ];
    }
}
