<?php

namespace App\Console\Commands;

use App\Models\Delivery\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class ShipmentCostRecalc
 * @package App\Console\Commands
 */
class ShipmentCostRecalc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipment:cost_recalc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Пересчёт стоимости у отправлений';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Shipment[] $shipments */
        $shipments = Shipment::query()->with('basketItems')->get();
        foreach ($shipments as $shipment) {
            $shipment->costRecalc();
        }
    }
}
