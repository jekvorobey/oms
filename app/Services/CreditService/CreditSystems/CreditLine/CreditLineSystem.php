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
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->creditLineService = resolve(SDK\CreditLine::class);
        $this->logger = Log::channel('payments');
    }

}
