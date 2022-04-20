<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Services\CargoService;
use Exception;
use Illuminate\Console\Command;

/**
 * Class CheckShipmentInCargoStatus
 * @package App\Console\Commands
 */
class CheckCargoShipmentsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:cargos_shipments_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверку статуса забираемого отправления в день даты забора груза';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(CargoService $cargoService)
    {
        Cargo::query()->whereDate('intake_date', today())->each(function (Cargo $cargo) use ($cargoService) {
            $cargoService->checkShipmentsStatusInCargo($cargo);
        });
    }
}
