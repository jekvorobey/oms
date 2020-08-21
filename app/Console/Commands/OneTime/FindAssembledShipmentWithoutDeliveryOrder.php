<?php

namespace App\Console\Commands\OneTime;

use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class FindAssembledShipmentWithoutDeliveryOrder
 * @package App\Console\Commands\OneTime
 */
class FindAssembledShipmentWithoutDeliveryOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipment:find_assembled_shipment_without_delivery_order {--fix : delete shipments from cargo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Найти собранные отправление, которые добавлены в груз, но не имеют заказа на доставку';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Shipment[] $shipments */
        $shipments = Shipment::query()
            ->where('status', '>=', ShipmentStatus::ASSEMBLED)
            ->whereNotNull('cargo_id')
            ->whereHas('delivery', function(Builder $query) {
                $query->whereNull('xml_id');
            })
            ->with('delivery.order')
            ->get();
        foreach ($shipments as $shipment) {
            $this->output->text([$shipment->id, $shipment->number]);
            if ($this->option('fix')) {
                $shipment->cargo_id = null;
                $shipment->save();
            }
        }
    }
}
