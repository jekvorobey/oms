<?php

namespace App\Console\Commands\OneTime;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Services\OfferService\OfferService;

/**
 * Есть посылки у которых остался статус "Ожидается отмена", хотя посылки уже получены.
 * Синхронизируем статусы этих посылок со статусами доставок
 */
class UpdateShipmentStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipment:update_statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновить статусы посылок';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shipments = Shipment::query()
            ->with('delivery')
            ->where('status', ShipmentStatus::CANCELLATION_EXPECTED)
            ->whereHas('delivery', function (Builder $query) {
                $query->where('status', DeliveryStatus::DONE);
            })->get();

        /** @var Shipment $shipment */
        foreach ($shipments as $shipment) {
            $this->getOutput()->info(sprintf(
                'Shipment %d: ShipmentStatus: %s, DeliveryStatus: %s',
                $shipment->id, ShipmentStatus::all()[$shipment->status]->name, DeliveryStatus::all()[$shipment->delivery->status]->name
            ));

            $shipment->status = ShipmentStatus::DONE;
            $shipment->save();
        }
    }
}
