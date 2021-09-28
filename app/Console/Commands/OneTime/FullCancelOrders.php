<?php

namespace App\Console\Commands\OneTime;

use App\Models\Delivery\Delivery;
use App\Models\Order\Order;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class FullCancelOrders
 * @package App\Console\Commands\OneTime
 */
class FullCancelOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:full_cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправлеяет возможный баг отмены всех доставок и отправлений по отмененным заказам/доставкам';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService, DeliveryService $deliveryService)
    {
        /** @var Collection|Order[] $orders */
        $orders = Order::query()
            ->where('is_canceled', true)
            ->with('deliveries.shipments')
            ->get();
        foreach ($orders as $order) {
            try {
                $orderService->cancel($order);
            } catch (\Throwable $e) {
            }

            foreach ($order->deliveries as $delivery) {
                try {
                    $deliveryService->cancelDelivery($delivery, $order->return_reason_id);
                } catch (\Throwable $e) {
                }

                foreach ($delivery->shipments as $shipment) {
                    try {
                        $deliveryService->cancelShipment($shipment, $order->return_reason_id);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        /** @var Collection|Delivery[] $deliveries */
        $deliveries = Delivery::query()
            ->where('is_canceled', true)
            ->with('shipments')
            ->get();
        foreach ($deliveries as $delivery) {
            try {
                $deliveryService->cancelDelivery($delivery, $delivery->return_reason_id);
            } catch (\Throwable $e) {
            }

            foreach ($delivery->shipments as $shipment) {
                try {
                    $deliveryService->cancelShipment($shipment, $shipment->return_reason_id);
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
