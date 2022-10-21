<?php

namespace App\Console\Commands;

use App\Services\DeliveryService;
use Exception;
use Illuminate\Console\Command;

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
     * @throws Exception
     */
    public function handle(DeliveryService $deliveryService)
    {
        $deliveryService->updateDeliveryStatusFromDeliveryService();
    }
}
