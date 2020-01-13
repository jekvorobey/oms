<?php

namespace App\Console\Commands;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\SenderDto;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Store\Dto\StorePickupTimeDto;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use MerchantManagement\Services\MerchantService\MerchantService;

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
    public function handle()
    {
        /** @var Cargo $cargo */
        $cargo = Cargo::query()
            ->where('status', CargoStatus::STATUS_CREATED)
            ->where('store_id', $this->argument('storeId'))
            ->where('delivery_service', $this->argument('deliveryService'))
            ->whereHas('shipments')
            ->with('shipments.delivery')
            ->orderBy('created_at')
            ->first();
        if (!is_null($cargo)) {
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            $storeQuery = $storeService->newQuery()
                ->include('storeContact')
                ->include('storePickupTime');
            $store = $storeService->store($cargo->store_id, $storeQuery);
            if (is_null($store)) {
                //todo Добавить оповещение о невыгруженном грузе
                return;
            }

            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);
            $merchant = $merchantService->merchant($store->merchant_id);

            $courierCallInputDto = new CourierCallInputDto();

            $senderDto = new SenderDto();
            $courierCallInputDto->sender = $senderDto;
            $senderDto->address_string = isset($store->address['address_string']) ? $store->address['address_string'] : '';
            $senderDto->post_index = isset($store->address['post_index']) ? $store->address['post_index'] : '';
            $senderDto->country_code = isset($store->address['country_code']) ? $store->address['country_code'] : '';
            $senderDto->region = isset($store->address['region']) ? $store->address['region'] : '';
            $senderDto->area = isset($store->address['area']) ? $store->address['area'] : '';
            $senderDto->city = isset($store->address['city']) ? $store->address['city'] : '';
            $senderDto->city_guid = isset($store->address['city_guid']) ? $store->address['city_guid'] : '';
            $senderDto->street = isset($store->address['street']) ? $store->address['street'] : '';
            $senderDto->house = isset($store->address['house']) ? $store->address['house'] : '';
            $senderDto->block = isset($store->address['block']) ? $store->address['block'] : '';
            $senderDto->flat = isset($store->address['flat']) ? $store->address['flat'] : '';
            $senderDto->company_name = $merchant->legal_name;
            $senderDto->contact_name = !is_null($store->storeContact()) ? $store->storeContact()[0]->name : '';
            $senderDto->email = !is_null($store->storeContact()) ? $store->storeContact()[0]->email : '';
            $senderDto->phone = !is_null($store->storeContact()) ? $store->storeContact()[0]->phone : '';

            $deliveryCargoDto = new DeliveryCargoDto();
            $courierCallInputDto->cargo = $deliveryCargoDto;
            $deliveryCargoDto->weight = $cargo->weight;
            $deliveryCargoDto->width = $cargo->width;
            $deliveryCargoDto->height = $cargo->height;
            $deliveryCargoDto->length = $cargo->length;
            foreach ($cargo->shipments as $shipment) {
                $deliveryCargoDto->order_ids[] = $shipment->delivery->xml_id;
            }

            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);

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
                $dayPlus++;

                //Получаем номер дня недели (1 - понедельник, ..., 7 - воскресенье)
                $dayOfWeek = $date->format('N');
                if (!$storePickupTimes->has($dayOfWeek)) {
                    continue;
                }
                $deliveryCargoDto->date = $date->format('d.m.Y');
                $deliveryCargoDto->time = $storePickupTimes[$dayOfWeek]->pickup_time;

                try {
                    $courierCallOutputDto = $courierCallService->createCourierCall(
                        $cargo->delivery_service,
                        $courierCallInputDto
                    );
                    if ($courierCallOutputDto->success) {
                        $cargo->xml_id = $courierCallOutputDto->xml_id;
                        $cargo->status = CargoStatus::STATUS_REQUEST_SEND;

                        $cargo->save();
                        break;
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }
        }
    }
}
