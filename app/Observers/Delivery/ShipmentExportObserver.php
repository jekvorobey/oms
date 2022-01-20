<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentExport;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MerchantManagement\Services\MerchantService\MerchantService;
use MerchantManagement\Services\OperatorService\OperatorService;

class ShipmentExportObserver
{
    public function created(ShipmentExport $shipmentExport): void
    {
        if ($shipmentExport->err_code) {
            /** @var OperatorService $operatorService */
            $operatorService = app(OperatorService::class);
            /** @var ServiceNotificationService $notificationService */
            $notificationService = resolve(ServiceNotificationService::class);
            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);

            $query = $operatorService->newQuery()->setFilter('merchant_id', $shipmentExport->shipment->merchant_id);
            $operators = $operatorService->operators($query);
            $merchantInfo = $merchantService->merchant($shipmentExport->shipment->merchant_id);

            Log::debug(json_encode([
                '$operators' => $operators,
                'SHIPMENT_NUMBER' => $shipmentExport->shipment->number,
                'MERCHANT' => $merchantInfo->legal_name,
                'ERROR_MESSAGE' => $shipmentExport->err_message,
            ]));
            foreach ($operators as $operator) {
                $notificationService->send(
                    $operator->user_id,
                    Str::slug('Ошибка экспорта отправления', '_'),
                    [
                        'SHIPMENT_NUMBER' => $shipmentExport->shipment->number,
                        'MERCHANT' => $merchantInfo->legal_name,
                        'ERROR_MESSAGE' => $shipmentExport->err_message,
                    ]
                );
            }
        }
    }
}
