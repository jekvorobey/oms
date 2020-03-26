<?php

namespace App\Console\Commands;

use App\Models\Delivery\Delivery;
use App\Services\DeliveryService;
use Exception;
use Illuminate\Console\Command;

/**
 * Разовая команда!
 * Class DeliverOrderUpsert
 * @package App\Console\Commands
 */
class DeliverOrderUpsert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery_order:upsert {deliveryId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать/обновить заказы на доставку у служб доставок';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deliveries = Delivery::deliveriesAtWork(true);
        $deliveryId = $this->argument('deliveryId');

        if ($deliveries->isNotEmpty()) {
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);

            foreach ($deliveries as $delivery) {
                if ($deliveryId && $delivery->id != $deliveryId) {
                    continue;
                }

                try {
                    $deliveryService->saveDeliveryOrder($delivery);
                } catch (Exception $e) {
                }
            }
        }
    }
}
