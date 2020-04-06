<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Services\DeliveryService;
use Illuminate\Console\Command;

/**
 * Class CargoExport
 * @package App\Console\Commands
 */
class CargoExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cargo:export {storeId} {deliveryService}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Выгрузка грузов в службы доставки для заказа курьера';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(DeliveryService $deliveryService)
    {
        /** @var Cargo $cargo */
        $cargo = Cargo::query()
            ->where('status', CargoStatus::CREATED)
            ->where('store_id', $this->argument('storeId'))
            ->where('delivery_service', $this->argument('deliveryService'))
            ->whereHas('shipments')
            ->with('shipments.delivery')
            ->orderBy('created_at')
            ->first();
        if (!is_null($cargo)) {
            $deliveryService->createCourierCall($cargo);
        }
    }
}
