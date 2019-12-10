<?php

namespace App\Console\Commands;

use App\Models\Delivery\Delivery;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class UpdateDeliveriesStatus
 * @package App\Console\Commands
 */
class UpdateDeliveriesStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery:update_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновить статус доставок от служб доставок';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle()
    {
        $deliveries = Delivery::deliveriesAtWork();
        if ($deliveries->isNotEmpty()) {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            
            $deliveriesByService = $deliveries->groupBy('delivery_service');
            foreach ($deliveriesByService as $deliveryServiceId => $items) {
                try {
                    /** @var Collection|Delivery[] $items */
                    $deliveryOrderStatusDtos = $deliveryOrderService->statusOrders($deliveryServiceId,
                        $items->pluck('xml_id')->all());
                    foreach ($deliveryOrderStatusDtos as $deliveryOrderStatusDto) {
                        if ($deliveries->has($deliveryOrderStatusDto->number)) {
                            $delivery = $deliveries[$deliveryOrderStatusDto->number];
                            if ($deliveryOrderStatusDto->success) {
                                $delivery->status = $deliveryOrderStatusDto->status;
                                $delivery->status_xml_id = $deliveryOrderStatusDto->status_xml_id;
                                $delivery->status_xml_id_at = new Carbon($deliveryOrderStatusDto->status_date);
                                $delivery->save();
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }
}
