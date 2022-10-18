<?php

namespace App\Services;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Payment\PaymentStatus;
use Cms\Core\CmsException;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Greensight\CommonMsa\Dto\AbstractDto;
use Greensight\CommonMsa\Services\IbtService\IbtService;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\SenderDto;
use Greensight\Logistics\Dto\Lists\PointDto;
use Greensight\Logistics\Dto\Lists\ShipmentMethod;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderCostDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderInputDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderItemDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderPlaceDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\RecipientDto;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Store\Dto\StoreContactDto;
use Greensight\Store\Dto\StorePickupTimeDto;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MerchantManagement\Services\MerchantService\MerchantService;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Throwable;

/**
 * Класс-бизнес логики по работе с сущностями доставки:
 * - доставками
 * - товарами отправлений
 * - товарами коробок отправлений
 * Class DeliveryService
 * @package App\Services
 */
class DeliveryService
{
    /**
     * Получить объект отправления по его id
     *
     * @throws ModelNotFoundException
     */
    public function getDelivery(int $deliveryId): Delivery
    {
        return Delivery::findOrFail($deliveryId);
    }

    /**
     * Получить объект отправления по его идентификатору заказа на доставку в службе доставки
     */
    public function getDeliveryByXmlId(int $xmlId)
    {
        return Delivery::query()->where('xml_id', $xmlId)->first();
    }

    /**
     * Создать заявку на вызов курьера для забора груза
     * @throws Exception
     */
    public function createCourierCall(Cargo $cargo): void
    {
        if ($cargo->status != CargoStatus::CREATED) {
            throw new DeliveryServiceInvalidConditions('Груз не в статусе "Создан"');
        }
        if ($cargo->xml_id) {
            throw new DeliveryServiceInvalidConditions(
                'Для груза уже создана заявка на вызов курьера с номером "' . $cargo->xml_id . '"'
            );
        }
        if ($cargo->shipments->isEmpty()) {
            throw new DeliveryServiceInvalidConditions('Груз не содержит отправлений');
        }

        /** @var StoreService $storeService */
        $storeService = resolve(StoreService::class);
        $storeQuery = $storeService->newQuery()->include('storeContact', 'storePickupTime');
        $store = $storeService->store($cargo->store_id, $storeQuery);
        if (is_null($store)) {
            $cargo->error_xml_id = 'Не найден склад с id="' . $cargo->store_id . '" для груза';
            $cargo->save();

            return;
        }

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $merchant = $merchantService->merchant($store->merchant_id);

        $courierCallInputDto = new CourierCallInputDto();

        /** @var StoreContactDto $storeContact */
        $storeContact = $store->storeContact()[0] ?? null;

        $senderDto = new SenderDto();
        $senderDto->address_string = $store->address['address_string'] ?? '';
        $senderDto->post_index = $store->address['post_index'] ?? '';
        $senderDto->country_code = $store->address['country_code'] ?? '';
        $senderDto->region = $store->address['region'] ?? '';
        $senderDto->area = $store->address['area'] ?? '';
        $senderDto->city = $store->address['city'] ?? '';
        $senderDto->city_guid = $store->address['city_guid'] ?? '';
        $senderDto->region_guid = $store->address['region_guid'] ?? '';
        $senderDto->street = $store->address['street'] ?? '';
        $senderDto->house = $store->address['house'] ?? '';
        $senderDto->block = $store->address['block'] ?? '';
        $senderDto->flat = $store->address['flat'] ?? '';
        $senderDto->comment = $store->address['comment'] ?? '';
        $senderDto->company_name = $merchant->legal_name;
        $senderDto->contact_name = $storeContact->name ?? '';
        $senderDto->email = $storeContact->email ?? '';
        $senderDto->phone = phoneNumberFormat(Str::before($storeContact->phone ?? '', ','));
        $courierCallInputDto->sender = $senderDto;

        if ($store->cdek_address && (!empty($store->cdek_address['address_string']) || !empty($store->cdek_address['code']))) {
            $cdekSenderDto = new SenderDto();
            $cdekSenderDto->address_string = $store->cdek_address['address_string'] ?? '';
            $cdekSenderDto->cdek_city_code = $store->cdek_address['code'] ?? '';
            $cdekSenderDto->post_index = $store->cdek_address['post_index'] ?? '';
            $cdekSenderDto->country_code = $store->cdek_address['country_code'] ?? '';
            $cdekSenderDto->region = $store->cdek_address['region'] ?? '';
            $cdekSenderDto->area = $store->cdek_address['area'] ?? '';
            $cdekSenderDto->city = $store->cdek_address['city'] ?? '';
            $cdekSenderDto->city_guid = $store->cdek_address['city_guid'] ?? '';
            $cdekSenderDto->street = $store->cdek_address['street'] ?? '';
            $cdekSenderDto->house = $store->cdek_address['house'] ?? '';
            $cdekSenderDto->block = $store->cdek_address['block'] ?? '';
            $cdekSenderDto->flat = $store->cdek_address['flat'] ?? '';
            $courierCallInputDto->cdekSender = $cdekSenderDto;
        }

        $cargo->recalc();

        $deliveryCargoDto = new DeliveryCargoDto();
        $deliveryCargoDto->weight = $cargo->weight;
        $deliveryCargoDto->width = $cargo->width;
        $deliveryCargoDto->height = $cargo->height;
        $deliveryCargoDto->length = $cargo->length;
        $orderIds = [];
        foreach ($cargo->shipments as $shipment) {
            $orderIds[] = $shipment->delivery->xml_id ?: 0;
        }
        $deliveryCargoDto->order_ids = $orderIds;

        //Получаем доступные дни недели для отгрузки грузов курьерам службы доставки текущего груза
        /** @var Collection|StorePickupTimeDto[] $storePickupTimes */
        $storePickupTimes = collect();
        if ($store->storePickupTime()) {
            for ($day = 1; $day <= 7; $day++) {
                //Ищем время отгрузки с учетом службы доставки
                /** @var StorePickupTimeDto $pickupTimeDto */
                $pickupTimeDto = $store->storePickupTime()->filter(function (StorePickupTimeDto $item) use (
                    $day,
                    $cargo
                ) {
                    return $item->day == $day &&
                        $item->delivery_service == $cargo->delivery_service &&
                        ($item->pickup_time_code || ($item->pickup_time_start && $item->pickup_time_end));
                })->first();

                if (!is_null($pickupTimeDto)) {
                    $storePickupTimes->put($day, $pickupTimeDto);
                }
            }
        }

        $dayPlus = 0;
        // @todo: fix get timezone by store
        $timezone = new DateTimeZone('Europe/Moscow');
        $dateNow = new DateTimeImmutable('now', $timezone);
        while ($dayPlus <= 6) {
            $date = $dateNow->modify('+' . $dayPlus . ' day' . ($dayPlus > 1 ? 's' : ''));
            //Получаем номер дня недели (1 - понедельник, ..., 7 - воскресенье)
            $dayOfWeek = $date->format('N');
            $dayPlus++;
            if (!$storePickupTimes->has($dayOfWeek)) {
                $cargo->error_xml_id = 'Возможно у склада не указан график отгрузки';
                continue;
            }

            $deliveryDateTo = new DateTimeImmutable($date->format('Y-m-d') . 'T' . $storePickupTimes[$dayOfWeek]->pickup_time_end, $timezone);
            if ($dateNow > $deliveryDateTo) {
                continue;
            }

            $deliveryCargoDto->date = $date->format('d.m.Y');
            $deliveryCargoDto->time_code = $storePickupTimes[$dayOfWeek]->pickup_time_code;
            $deliveryCargoDto->time_start = $storePickupTimes[$dayOfWeek]->pickup_time_start;
            $deliveryCargoDto->time_end = $storePickupTimes[$dayOfWeek]->pickup_time_end;

            $courierCallInputDto->cargo = $deliveryCargoDto;
            try {
                /** @var CourierCallService $courierCallService */
                $courierCallService = resolve(CourierCallService::class);
                $courierCallOutputDto = $courierCallService->createCourierCall(
                    $cargo->delivery_service,
                    $courierCallInputDto
                );
                if ($courierCallOutputDto->success) {
                    $cargo->xml_id = $courierCallOutputDto->xml_id;
                    $cargo->error_xml_id = $courierCallOutputDto->special_courier_call_status;
                    $cargo->intake_date = $date;
                    $cargo->intake_time_from = $deliveryCargoDto->time_start;
                    $cargo->intake_time_to = $deliveryCargoDto->time_end;
                    break;
                }

                $cargo->error_xml_id = $courierCallOutputDto->message;
            } catch (Throwable $e) {
                $cargo->error_xml_id = $e->getMessage();
                report($e);
            }
        }
        if ($cargo->error_xml_id) {
            $cargo->save();
            throw new Exception($cargo->error_xml_id);
        }

        $cargo->save();
    }

    /**
     * Отменить заявку на вызов курьера для забора груза
     */
    public function cancelCourierCall(Cargo $cargo): void
    {
        if ($cargo->xml_id) {
            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);
            $courierCallService->cancelCourierCall($cargo->delivery_service, $cargo->xml_id);
            $cargo->xml_id = '';
            $cargo->cdek_intake_number = null;
            $cargo->error_xml_id = '';
            $cargo->save();
        }
    }

    /**
     * Проверить ошибки в заявке на вызов курьера во внешнем сервисе
     */
    public function checkExternalStatus(Cargo $cargo): void
    {
        if ($cargo->xml_id) {
            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);
            $status = $courierCallService->checkExternalStatus($cargo->delivery_service, $cargo->xml_id);
            $cargo->error_xml_id = $status->error;
            $cargo->cdek_intake_number = $status->intake_number;
            $cargo->updated_at = Carbon::now();
            $cargo->save();
        }
    }

    /**
     * Сохранить (создать или обновить) заказ на доставку с службе доставке
     * @throws Exception
     */
    public function saveDeliveryOrder(Delivery $delivery): void
    {
        $delivery->loadMissing('shipments');
        /**
         * Проверяем, что товары по все отправлениям доставки собраны
         */
        $cntShipments = 0;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }

            $validShipmentStatuses = [
                ShipmentStatus::ASSEMBLED,
            ];

            if (!in_array($shipment->status, $validShipmentStatuses)) {
                throw new DeliveryServiceInvalidConditions(
                    'Не все отправления доставки подтверждены/собраны мерчантами'
                );
            }

            $cntShipments++;
        }

        if (!$cntShipments) {
            throw new DeliveryServiceInvalidConditions(
                'Не все отправления доставки подтверждены/собраны мерчантами'
            );
        }

        $deliveryOrderInputDto = $this->formDeliveryOrder($delivery);
        try {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);

            if (!$delivery->xml_id) {
                $deliveryOrderOutputDto = $deliveryOrderService->createOrder(
                    $delivery->delivery_service,
                    $deliveryOrderInputDto
                );
                if ($deliveryOrderOutputDto->success && $deliveryOrderOutputDto->xml_id) {
                    if ($deliveryOrderOutputDto->tracknumber) {
                        $delivery->tracknumber = $deliveryOrderOutputDto->tracknumber;
                    }
                    if ($deliveryOrderOutputDto->barcode) {
                        $delivery->barcode = $deliveryOrderOutputDto->barcode;
                    }
                    $delivery->error_xml_id = '';
                } else {
                    $delivery->error_xml_id = $deliveryOrderOutputDto->message;
                }
                if ($deliveryOrderOutputDto->xml_id) {
                    $delivery->xml_id = $deliveryOrderOutputDto->xml_id;
                }
                $delivery->save();
                foreach ($delivery->shipments as $shipment) {
                    if (!$shipment->cargo_id) {
                        $shipment->updated_at = now();
                        $shipment->save();
                    }
                }
            } else {
                $deliveryOrderOutputDto = $deliveryOrderService->updateOrder(
                    $delivery->delivery_service,
                    $delivery->xml_id,
                    $deliveryOrderInputDto
                );
                if ($deliveryOrderOutputDto->success) {
                    $delivery->error_xml_id = '';
                } else {
                    $delivery->error_xml_id = $deliveryOrderOutputDto->message;
                }

                /**
                 * Т.к. в DPD вместо обновления заказа нужно отменять предыдущий и создавать новый,
                 * то нужно перезаписывать xml_id в $delivery
                 */
                if ($delivery->delivery_service == \Greensight\Logistics\Dto\Lists\DeliveryService::SERVICE_DPD
                    && isset($deliveryOrderOutputDto->xml_id)
                    && !empty($deliveryOrderOutputDto->xml_id)
                    && $delivery->xml_id != $deliveryOrderOutputDto->xml_id
                ) {
                    $delivery->xml_id = $deliveryOrderOutputDto->xml_id;
                }

                $delivery->save();
            }

            /**
             * Указываем информация о кодах мест (коробок) в службе доставки
             */
            if ($deliveryOrderOutputDto->success && $deliveryOrderOutputDto->places->isNotEmpty()) {
                foreach ($deliveryOrderOutputDto->places as $place) {
                    foreach ($delivery->shipments as $shipment) {
                        foreach ($shipment->packages as $package) {
                            if ($place->code == $package->id || $place->code == $shipment->number) {
                                $package->xml_id = $place->code_xml_id;
                                $package->save();
                                break 2;
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $delivery->error_xml_id = $e->getMessage();
            $delivery->save();
            report($e);
        }
    }

    /**
     * Сформировать заказ на доставку
     * @throws CmsException
     */
    protected function formDeliveryOrder(Delivery $delivery): DeliveryOrderInputDto
    {
        $delivery->loadMissing(['shipments.packages.items.basketItem']);
        $deliveryOrderInputDto = new DeliveryOrderInputDto();

        //Информация об получателе заказа
        $recipientDto = new RecipientDto((array) $delivery->delivery_address);
        $deliveryOrderInputDto->recipient = $recipientDto;

        //Информация о заказе
        $deliveryOrderDto = new DeliveryOrderDto();
        $deliveryOrderInputDto->order = $deliveryOrderDto;
        $deliveryOrderDto->number = $delivery->getDeliveryServiceNumber();
        $deliveryOrderDto->tracknumber = $delivery->tracknumber;
        $deliveryOrderDto->barcode = $delivery->barcode;
        $deliveryOrderDto->height = $delivery->height;
        $deliveryOrderDto->length = $delivery->length;
        $deliveryOrderDto->width = $delivery->width;
        $deliveryOrderDto->weight = $delivery->weight;
        $deliveryOrderDto->shipment_method = ShipmentMethod::METHOD_DS_COURIER;
        $deliveryOrderDto->delivery_method = $delivery->delivery_method;
        $deliveryOrderDto->tariff_id = $delivery->tariff_id;
        $deliveryOrderDto->delivery_date = $delivery->delivery_at->format(AbstractDto::DATE_FORMAT);
        $deliveryOrderDto->delivery_time_start = $delivery->delivery_time_start;
        $deliveryOrderDto->delivery_time_end = $delivery->delivery_time_end;
        $deliveryOrderDto->delivery_time_code = $delivery->delivery_time_code;
        $deliveryOrderDto->point_out_id = $delivery->point_id;
        $deliveryOrderDto->description = $recipientDto->comment;

        //Информация о стоимости заказа
        $deliveryOrderCostDto = new DeliveryOrderCostDto();
        $deliveryOrderInputDto->cost = $deliveryOrderCostDto;
        /**
         * Когда будет постоплата, указать стоимость доставки в поле ниже.
         * Уточнить, как рассчитывается стоимость доставки для одного заказа на доставку.
         * Наверное берется стоимость доставки для всего заказа и делится на кол-во доставок:
         * round($delivery->order->delivery_price / $delivery->order->deliveries->count(), 2)
         */
        $deliveryOrderCostDto->delivery_cost = 0;

        $deliveryOrderCostDto->cod_cost = 0;
        /** @var Shipment $shipment */
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }
            $deliveryOrderCostDto->cod_cost += $shipment->basketItems->sum('cost');
        }
        if ($delivery->isPostPaid()) {
            $deliveryOrderCostDto->delivery_cost_pay = round($delivery->order->delivery_price / $delivery->order->deliveries->count(), 2);
            $deliveryOrderCostDto->is_delivery_payed_by_recipient = true;
        } else {
            $deliveryOrderCostDto->delivery_cost_pay = 0;
            $deliveryOrderCostDto->is_delivery_payed_by_recipient = false;
        }
        $deliveryOrderCostDto->assessed_cost = $deliveryOrderCostDto->cod_cost;

        //Информация об отправителе заказа
        if ($delivery->shipments->count() == 1) {
            /**
             * Если в доставке одно отправление, то в качестве данных отправителя указываем данные мерчанта
             */
            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);
            $shipment = $delivery->shipments[0];
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            $store = $storeService->store($shipment->store_id, $storeService->newQuery()->include('storeContact'));
            $merchant = $merchantService->merchant($shipment->merchant_id);

            $storeAddress = $store->address;
            $storeAddress['street'] = $storeAddress['street'] ?: '-'; //у cdek и b2cpl улица обязательна
            $senderDto = new DeliveryOrderInput\SenderDto($storeAddress);
            $deliveryOrderInputDto->sender = $senderDto;
            // если есть доп адрес для сдэка, то его тоже передаем
            $cdekSenderAddress = $store->cdek_address;
            if ($cdekSenderAddress && !empty($cdekSenderAddress['address_string'])) {
                $cdekSenderAddress['street'] = $cdekSenderAddress['street'] ?: '-';
                $deliveryOrderInputDto->cdekSender = $cdekSenderAddress;
            }

            $senderDto->is_seller = true;
            $senderDto->company_name = $merchant->legal_name;
            $senderDto->store_id = $shipment->store_id;
            $senderDto->inn = $merchant->inn;

            $storeContact = $store->storeContact[0];
            $senderDto->contact_name = $storeContact->name;
            $senderDto->email = $storeContact->email;
            $senderDto->phone = phoneNumberFormat($storeContact->phone);
            $senderDto->cdek_city_code = $cdekSenderAddress['code'] ?? null;
            $senderDto->comment = $storeAddress['comment'] ?? null;

            if (count($store->storeContact) > 1) {
                $senderDto->additional_contacts = collect();
                foreach ($store->storeContact->slice(1) as $additionalStoreContact) {
                    $senderDto->additional_contacts->add(new StoreContactDto([
                        'contact_name' => $additionalStoreContact->name,
                        'phone' => phoneNumberFormat($additionalStoreContact->phone),
                        'email' => $additionalStoreContact->email,
                    ]));
                }
            }
        } else {
            /**
             * Иначе указываем данные маркетплейса
             */
            /** @var OptionService $optionService */
            $optionService = resolve(OptionService::class);
            /** @var IbtService $ibtService */
            $ibtService = resolve(IbtService::class);
            //todo Переделать получение адреса склада iBT из OptionService
            $centralStoreAddress = $ibtService->getCentralStoreAddress();
            $senderDto = new DeliveryOrderInput\SenderDto($centralStoreAddress);
            $deliveryOrderInputDto->sender = $senderDto;
            $marketplaceData = $optionService->get([
                OptionDto::KEY_ORGANIZATION_CARD_FULL_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_LAST_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_FIRST_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_MIDDLE_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_EMAIL,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_PHONE,
                OptionDto::KEY_ORGANIZATION_CARD_FACT_ADDRESS,
            ]);
            $senderDto->address_string = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_FACT_ADDRESS];
            $senderDto->company_name = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_FULL_NAME];
            $senderDto->contact_name = join(' ', [
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_LAST_NAME],
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_FIRST_NAME],
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_MIDDLE_NAME],
            ]);
            $senderDto->email = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_EMAIL];
            $senderDto->phone = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_PHONE];
        }
        //Для самовывоза указываем адрес ПВЗ
        if (!$delivery->delivery_address && $delivery->point_id) {
            /** @var ListsService $listsService */
            $listsService = resolve(ListsService::class);
            $pointQuery = $listsService->newQuery()
                ->setFilter('id', $delivery->point_id)
                ->addFields(PointDto::entity(), 'address', 'city_guid', 'cdek_city_code');
            /** @var PointDto|null $pointDto */
            $pointDto = $listsService->points($pointQuery)->first();
            if ($pointDto) {
                $recipientDto->post_index = $pointDto->address['post_index'] ?? '';
                $recipientDto->region = $pointDto->address['region'] ?? '';
                $recipientDto->area = $pointDto->address['area'] ?? '';
                $recipientDto->city = $pointDto->address['city'] ?? '';
                $recipientDto->city_guid = $pointDto->city_guid;
                $recipientDto->cdek_city_code = $pointDto->cdek_city_code;
                $recipientDto->street = $pointDto->address['street'] ?: '-'; //у cdek и b2cpl улица обязательна
                $recipientDto->house = $pointDto->address['house'] ?? '';
                $recipientDto->block = $pointDto->address['block'] ?? '';
                $recipientDto->flat = $pointDto->address['flat'] ?? '';
            }
        }

        $recipientDto->address_string = implode(', ', array_filter([
            $recipientDto->post_index,
            $recipientDto->region != $recipientDto->city ? $recipientDto->region : '',
            $recipientDto->area,
            $recipientDto->city,
            $recipientDto->street,
            $recipientDto->house,
            $recipientDto->block,
            $recipientDto->flat,
        ]));
        $recipientDto->street = $recipientDto->street ?: 'нет'; //у cdek и b2cpl улица обязательна
        $recipientDto->contact_name = $delivery->receiver_name;
        $recipientDto->email = $delivery->receiver_email;
        $recipientDto->phone = phoneNumberFormat($delivery->receiver_phone);

        //Информация о местах (коробках) заказа
        $places = collect();
        $deliveryOrderInputDto->places = $places;
        $packageNumber = 1;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }

            if ($shipment->packages && $shipment->packages->isNotEmpty()) {
                foreach ($shipment->packages as $package) {
                    $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                    $places->push($deliveryOrderPlaceDto);
                    $deliveryOrderPlaceDto->number = $packageNumber++;
                    $deliveryOrderPlaceDto->code = $package->id;
                    $deliveryOrderPlaceDto->width = (int) ceil($package->width);
                    $deliveryOrderPlaceDto->height = (int) ceil($package->height);
                    $deliveryOrderPlaceDto->length = (int) ceil($package->length);
                    $deliveryOrderPlaceDto->weight = (int) ceil($package->weight);
                    $deliveryOrderPlaceDto->package_id = $package->xml_id;

                    $items = collect();
                    $deliveryOrderPlaceDto->items = $items;
                    foreach ($package->items as $item) {
                        $basketItem = $item->basketItem;
                        $deliveryOrderItemDto = new DeliveryOrderItemDto();
                        $items->push($deliveryOrderItemDto);
                        $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                        $deliveryOrderItemDto->name = $basketItem->name;
                        $deliveryOrderItemDto->quantity = (float) $item->qty;
                        $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int) ceil($basketItem->product['height']) : 0;
                        $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int) ceil($basketItem->product['width']) : 0;
                        $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int) ceil($basketItem->product['length']) : 0;
                        $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int) ceil($basketItem->product['weight']) : 0;
                        $deliveryOrderItemDto->cost = round($item->qty > 0 ? $basketItem->price / $item->qty : 0, 2);
                        if ($delivery->isPostPaid()) {
                            $deliveryOrderItemDto->price = $item->qty > 0 ? $basketItem->price / $item->qty : 0;
                        } else {
                            $deliveryOrderItemDto->price = 0;
                        }
                        $deliveryOrderItemDto->assessed_cost = $deliveryOrderItemDto->cost;
                    }
                }
            } else {
                $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                $places->push($deliveryOrderPlaceDto);
                $deliveryOrderPlaceDto->number = $packageNumber++;
                $deliveryOrderPlaceDto->code = $shipment->number;
                $deliveryOrderPlaceDto->width = (int) ceil($shipment->width);
                $deliveryOrderPlaceDto->height = (int) ceil($shipment->height);
                $deliveryOrderPlaceDto->length = (int) ceil($shipment->length);
                $deliveryOrderPlaceDto->weight = (int) ceil($shipment->weight);

                $items = collect();
                $deliveryOrderPlaceDto->items = $items;
                foreach ($shipment->items as $item) {
                    $basketItem = $item->basketItem;
                    $deliveryOrderItemDto = new DeliveryOrderItemDto();
                    $items->push($deliveryOrderItemDto);
                    $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                    $deliveryOrderItemDto->name = $basketItem->name;
                    $deliveryOrderItemDto->quantity = (float) $basketItem->qty;
                    $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int) ceil($basketItem->product['height']) : 0;
                    $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int) ceil($basketItem->product['width']) : 0;
                    $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int) ceil($basketItem->product['length']) : 0;
                    $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int) ceil($basketItem->product['weight']) : 0;
                    $deliveryOrderItemDto->cost = round($basketItem->qty > 0 ? $basketItem->cost / $basketItem->qty : 0, 2);
                    if ($delivery->isPostPaid()) {
                        $deliveryOrderItemDto->price = $item->qty > 0 ? $basketItem->price / $item->qty : 0;
                    } else {
                        $deliveryOrderItemDto->price = 0;
                    }
                    $deliveryOrderItemDto->assessed_cost = $deliveryOrderItemDto->cost;
                }
            }
        }

        return $deliveryOrderInputDto;
    }

    /**
     * Получить от служб доставок и обновить статусы заказов на доставку
     */
    public function updateDeliveryStatusFromDeliveryService(): void
    {
        /** @var Collection $deliveries */
        $deliveries = Delivery::deliveriesInDelivery()->load('order');
        if ($deliveries->isEmpty()) {
            return;
        }

        $deliveriesByServices = $deliveries->groupBy('delivery_service');

        /** @var Collection|Delivery[] $deliveriesByService */
        foreach ($deliveriesByServices as $deliveryServiceId => $deliveriesByService) {
            switch ($deliveryServiceId) {
                case LogisticsDeliveryService::SERVICE_DPD:
                    $deliveriesByService = $deliveriesByService->keyBy(fn($deliveryByService) => $deliveryByService->getDeliveryServiceNumber());
                    break;
                default:
                    $deliveriesByService = $deliveriesByService->keyBy('xml_id');
                    break;
            }

            try {
                /** @var DeliveryOrderService $deliveryOrderService */
                $deliveryOrderService = resolve(DeliveryOrderService::class);
                $deliveryOrderStatusDtos = $deliveryOrderService->statusOrders(
                    $deliveryServiceId,
                    $deliveriesByService->keys()->all()
                );
            } catch (Throwable $e) {
                report($e);
                continue;
            }

            foreach ($deliveryOrderStatusDtos as $deliveryOrderStatusDto) {
                $orderNumberOrXmlId = $deliveryServiceId == LogisticsDeliveryService::SERVICE_DPD
                    ? $deliveryOrderStatusDto->number
                    : $deliveryOrderStatusDto->xml_id;

                $delivery = $deliveriesByService[$orderNumberOrXmlId] ?? null;
                if (!$delivery) {
                    continue;
                }

                if (!$deliveryOrderStatusDto->success) {
                    continue;
                }

                try {
                    if ($deliveryOrderStatusDto->status && $delivery->status != $deliveryOrderStatusDto->status) {
                        $delivery->status = $deliveryOrderStatusDto->status;

                        if ($delivery->isPostPaid()) {
                            if ($delivery->status === DeliveryStatus::DONE) {
                                $delivery->payment_status = PaymentStatus::PAID;
                            } elseif (in_array($delivery->status, [DeliveryStatus::CANCELLATION_EXPECTED, DeliveryStatus::RETURNED])) {
                                $delivery->payment_status = PaymentStatus::TIMEOUT;
                            }
                        }
                    }

                    $delivery->setStatusXmlId(
                        $deliveryOrderStatusDto->status_xml_id,
                        new Carbon($deliveryOrderStatusDto->status_date)
                    );

                    if (empty($delivery->xml_id) && $deliveryOrderStatusDto->xml_id) {
                        $delivery->xml_id = $deliveryOrderStatusDto->xml_id;

                        if (!empty($delivery->error_xml_id) && empty($deliveryOrderStatusDto->message)) {
                            $delivery->error_xml_id = null;
                        }
                    }

                    $delivery->save();
                } catch (Throwable $e) {
                    logger()->error("Error when updating status of Delivery #{$delivery->id} ({$delivery->xml_id})");
                    report($e);
                    continue;
                }
            }
        }
    }

    /**
     * Получить от сдэк статус и обновить статус заказа на доставку
     * @param Model|Delivery $delivery
     * @param array $data
     */
    public function updateDeliveryStatus($delivery, array $data): void
    {
        $delivery->setStatusXmlId(
            $data['statusCode'],
            new Carbon($data['status_date'])
        );
        $delivery->status = $data['status'];
        $delivery->save();
    }

    /**
     * Получить id службы доставки на нулевой миле для отправления.
     * Сначала проверяется, не указана ли служба доставки у самого отправления:
     * если указана, то возвращается она, иначе берется служба доставки у доставки, в которую входит отправление
     */
    public function getZeroMileShipmentDeliveryServiceId(Shipment $shipment): int
    {
        return $shipment->delivery_service_zero_mile ?: $shipment->delivery->delivery_service;
    }

    /**
     * Отменить доставку
     * @throws Exception
     */
    public function cancelDelivery(Delivery $delivery, ?int $orderReturnReasonId = null): bool
    {
        if ($delivery->status === DeliveryStatus::DONE) {
            throw new DeliveryServiceInvalidConditions(
                'Доставку со статусом "Доставлена получателю" нельзя отменить'
            );
        }

        $delivery->is_canceled = true;

        $delivery->return_reason_id ??= $orderReturnReasonId;

        if (!$delivery->save()) {
            return false;
        }

        rescue(fn() => $this->cancelDeliveryOrder($delivery));

        return true;
    }

    /**
     * Отменить заказ на доставку у службы доставки (ЛО)
     */
    public function cancelDeliveryOrder(Delivery $delivery): void
    {
        $xmlId = $delivery->xml_id;

        //отмена в DPD по внутреннему номеру
        if ($delivery->delivery_service === LogisticsDeliveryService::SERVICE_DPD && empty($xmlId)) {
            $xmlId = $delivery->getDeliveryServiceNumber();
        }

        if ($delivery->delivery_service === LogisticsDeliveryService::SERVICE_CDEK && $delivery->status >= DeliveryStatus::ASSEMBLED) {
            return;
        }

        if ($xmlId) {
            // IBT-621: не удалять xml_id при отмене доставки
            // $delivery->xml_id = '';
            // $delivery->save();

            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            $deliveryOrderService->cancelOrder($delivery->delivery_service, $xmlId);
        }
    }

    /**
     * Пометить доставку как проблемную
     */
    public function markAsProblem(Delivery $delivery): bool
    {
        $delivery->is_problem = true;

        return $delivery->save();
    }

    /**
     * Отменить флаг проблемности у доставки, если все отправления непроблемные
     */
    public function markAsNonProblem(Delivery $delivery): bool
    {
        $isAllShipmentsOk = true;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_problem) {
                $isAllShipmentsOk = false;
                break;
            }
        }
        $delivery->is_problem = !$isAllShipmentsOk;

        return $delivery->save();
    }
}
