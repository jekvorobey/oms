<?php

namespace App\Services\CreditService\CreditSystems\CreditLine;

use App\Services\CreditService\CreditSystems\CreditSystemInterface;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

/**
 * Class CreditLineSystem
 * @package App\Services\CreditService\CreditSystems\CreditLine
 */
class CreditLineSystem implements CreditSystemInterface
{
    /** @var SDK\CreditLine */
    private $creditLineService;
    /** @var Logger */
    private $logger;

    /**
     * CreditLineSystem constructor.
     */
    public function __construct()
    {
        $this->creditLineService = resolve(SDK\CreditLine::class);
        $this->logger = Log::channel('payments');

        $this->creditLineService->auth('https://s1.l-kredit.ru/internetshopcreditlinework/iscl.svc?singleWsdl', 'ibt.ru', 'EbR9ga6PwB');
    }

    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function checkCreditOrder(string $id): array
    {
        $creditOrder = $this->creditLineService->getOrderStatus($id);

        return [
            'status' => $creditOrder->getStatus(),
            'discount' => $creditOrder->getDiscount(),
            'numOrder' => $creditOrder->getNumOrder(),
            'confirm' => $creditOrder->getConfirm(),
            'initPay' => $creditOrder->getInitPay(),
            'bankCode' => $creditOrder->getBankCode(),
        ];
    }
}
