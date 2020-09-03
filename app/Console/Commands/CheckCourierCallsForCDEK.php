<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use Greensight\Logistics\Dto\CourierCall\ExternalStatusCheckDto;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use App\Services\DeliveryService as OMSDeliveryService;
use Illuminate\Console\Command;

class CheckCourierCallsForCDEK extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:CDEKCourierCalls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get actual info about all Courier Requests (CDEK only)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $deliveryService = resolve(OMSDeliveryService::class);
        $cargos = Cargo::query()
            ->where('delivery_service', DeliveryService::SERVICE_CDEK)
            ->where('error_xml_id', '=',ExternalStatusCheckDto::CDEK_STATUS_ACCEPTED)
            ->get();

        /** @var Cargo $cargo */
        foreach ($cargos as $cargo) {
            $deliveryService->checkExternalStatus($cargo);
        }
    }
}
