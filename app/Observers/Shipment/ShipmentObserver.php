<?php

namespace App\Observers\Shipment;

use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use MerchantManagement\Services\MerchantService\MerchantService;

class ShipmentObserver
{
    /**
     * Handle the shipment "updated" event.
     * @return void
     */
    public function updated(Shipment $shipment)
    {
        $merchantService = resolve(MerchantService::class);
        $merchant = $merchantService->newQuery()
            ->addFields('id', 'is_require_approval')
            ->merchant($shipment->merchant_id);

        if ($shipment->payment_status === 3 && $merchant->is_require_approval) {
            $shipment->status = ShipmentStatus::CHECKING;
        }

        Shipment::withoutEvents(fn() => $shipment->save());
    }
}
