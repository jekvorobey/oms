<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLRequest;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLResponse;

interface CreditLineInterface
{
    public function confirmOrganization(): ?bool;

    public function processCreditLineApplication(CLRequest $request): CLResponse;

    public function getOrderStatus($orderId);

    public function getOrderReport(string $startDate, string $endDate);
}
