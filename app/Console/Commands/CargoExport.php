<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\SenderDto;
use Greensight\Logistics\Dto\Lists\CourierCallTime\B2CplCourierCallTime;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Store\Dto\StorePickupTimeDto;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

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
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var Cargo $cargo */
        $cargo = Cargo::query()
            ->where('status', CargoStatus::STATUS_CREATED)
            ->where('store_id', $this->argument('storeId'))
            ->where('delivery_service', $this->argument('deliveryService'))
            ->groupBy('delivery_service')
            ->whereHas('shipments')
            ->with('shipments.delivery')
            ->orderBy('created_at')
            ->first();
        if (!is_null($cargo)) {
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            $storeQuery = $storeService->newQuery()
                ->include('storePickupTime');
            $store = $storeService->store($cargo->store_id, $storeQuery);
            if (!is_null($store)) {
                //todo Добавить оповещение о невыгруженном грузе
                return;
            }

            $courierCallInputDto = new CourierCallInputDto();

            $senderDto = new SenderDto();
            $courierCallInputDto->sender = $senderDto;
            $senderDto->address_string = join(', ', array_filter([
                $store->zip,
                $store->city,
                $store->city,
            ]));

            $deliveryCargoDto = new DeliveryCargoDto();
            $courierCallInputDto->cargo = $deliveryCargoDto;
            $deliveryCargoDto->weight = $cargo->weight;
            $deliveryCargoDto->width = $cargo->width;
            $deliveryCargoDto->height = $cargo->height;
            $deliveryCargoDto->length = $cargo->length;
            foreach ($cargo->shipments as $shipment) {
                $deliveryCargoDto->order_ids[] = $shipment->delivery->xml_id;
            }

            //Получаем доступные дни недели для отгрузки грузов курьерам службы доставки текущего груза
            /** @var Collection|StorePickupTimeDto[] $storePickupTimes */
            $storePickupTimes = collect();
            for ($day = 1; $day <= 7; $day++) {
                /** @var StorePickupTimeDto $pickupTimeDto */
                //Ищем время отгрузку с учетом службы доставки
                $pickupTimeDto = $store->storePickupTime()->filter(function (StorePickupTimeDto $item) use (
                    $day,
                    $cargo
                ) {
                    return $item->day == $day && $item->delivery_service == $cargo->delivery_service && $item->pickup_time;
                })->first();
                //Если не нашли, то выбираем общее время отгрузки для всех служб доставки
                if (is_null($pickupTimeDto)) {
                    $pickupTimeDto = $store->storePickupTime()->filter(function (StorePickupTimeDto $item
                    ) use (
                        $day
                    ) {
                        return $item->day == $day && $item->pickup_time;
                    })->first();
                }
                if (!is_null($pickupTimeDto)) {
                    $storePickupTimes->put($day, $pickupTimeDto);
                }
            }

            $dayPlus = 0;
            $date = new \DateTime();
            while ($dayPlus <= 6) {
                $date = $date->modify('+' . $dayPlus . 'day' . ($dayPlus > 1 ?  's': ''));
                //Получаем номер дня недели (1 - понедельник, ..., 7 - воскресенье)
                $dayOfWeek = $date->format('N');
                if (!$storePickupTimes->has($dayOfWeek)) {
                    continue;
                }
                $deliveryCargoDto->date = $date->format('d.m.Y');
                $deliveryCargoDto->time = $storePickupTimes[$dayOfWeek]->pickup_time;

                $dayPlus++;
            }
        }
    }
}
