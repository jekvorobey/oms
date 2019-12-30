<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\Lists\CourierCallTime\B2CplCourierCallTime;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Console\Command;

class CargoB2CplExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cargo:b2cpl_export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Выгрузка грузов в b2cpl для заказа курьера';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var Cargo $cargo */
        $cargo = Cargo::query()
            ->where('status', CargoStatus::STATUS_CREATED)
            ->where('delivery_service', DeliveryService::SERVICE_B2CPL)
            ->whereHas('shipments')
            ->orderBy('created_at')
            ->first();
        if (is_null($cargo)) {
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            $storeQuery = $storeService->newQuery()
                ->include('store');
            $store = $storeService->store($cargo->store_id, $storeQuery);

            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);

            $courierCallInputDto = new CourierCallInputDto();

            $deliveryCargoDto = new DeliveryCargoDto();
            $courierCallInputDto->cargo = $deliveryCargoDto;
            $deliveryCargoDto->date = (new \DateTime())->modify('1 day')->format('d.m.Y');
            //todo
        }
    }
}
