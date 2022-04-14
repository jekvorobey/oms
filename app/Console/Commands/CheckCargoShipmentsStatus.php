<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\ShipmentStatus;
use App\Services\DeliveryService;
use App\Services\ShipmentService;
use Exception;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
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
    protected $signature = 'check:cargo_shipments_status {cargoId}';

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
    public function handle(DeliveryService $deliveryService)
    {
        $cargoId = $this->argument('cargoId');
        /** @var Cargo $cargo */
        $cargo = Cargo::query()->whereKey($cargoId)->first();
        if (!is_null($cargo)) {
            throw new Exception("Груз с id=$cargoId не найден");
        }
        if (!is_null($cargo->intake_date)) {
            throw new Exception('Дата забора груза не установлена');
        }
        if ($cargo->intake_date->isToday()) {
            $cargo->loadMissing('shipments');
            $isNeedToCancelCourierCall = true;
            foreach ($cargo->shipments as $shipment) {
                if ($shipment->isInvalid()) {
                    $shipment->cargo_id = null;
                    $shipment->save();
                } else {
                    $isNeedToCancelCourierCall = false;
                }

                if ($shipment->status === ShipmentStatus::AWAITING_CONFIRMATION) {
                    //Отправка повторного уведомления мерчанту
                    /** @var ShipmentService $shipmentService */
                    $shipmentService = resolve(ShipmentService::class);
                    $shipmentService->sendShipmentNotification($shipment);

                    //Отправка повторного уведомления логистам
                    $attributes = [
                        'SHIPMENT_NUMBER' => $shipment->number,
                        'LINK_ORDER' => sprintf('%s/orders/%d', config('app.admin_host'), $shipment->delivery->order_id),
                        'LINK_CARGO' => sprintf('%s/orders/cargos/%d', config('app.admin_host'), $cargo->id),
                    ];
                    /** @var ServiceNotificationService $notificationService */
                    $notificationService = resolve(ServiceNotificationService::class);
                    $notificationService->sendByRole(RoleDto::ROLE_LOGISTIC, 'logist_otpravlenie_need_processing', $attributes);
                }
            }

            if ($isNeedToCancelCourierCall) {
                $deliveryService->cancelCourierCall($cargo);
            }
        }
    }
}
