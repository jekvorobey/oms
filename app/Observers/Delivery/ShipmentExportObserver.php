<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentExport;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Support\Str;
use MerchantManagement\Services\MerchantService\MerchantService;

class ShipmentExportObserver
{
    public function created(ShipmentExport $shipmentExport): void
    {
        if ($shipmentExport->err_code) {
            /** @var ServiceNotificationService $notificationService */
            $notificationService = resolve(ServiceNotificationService::class);
            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);

            $merchantInfo = $merchantService->merchant($shipmentExport->shipment->merchant_id);
            $notificationService->sendByRole(
                RoleDto::ROLE_LOGISTIC,
                'osibka_eksporta_otpravleniya',
                [
                    'SHIPMENT_NUMBER' => $shipmentExport->shipment->number,
                    'MERCHANT' => $merchantInfo->legal_name,
                    'ERROR_MESSAGE' => $shipmentExport->err_message,
                ]
            );
        }
    }
}
