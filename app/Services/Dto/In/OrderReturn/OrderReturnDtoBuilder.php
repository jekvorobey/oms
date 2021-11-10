<?php

namespace App\Services\Dto\In\OrderReturn;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use Illuminate\Support\Collection;
use Pim\Dto\Certificate\CertificateDto;
use Pim\Dto\Certificate\CertificateStatusDto;
use Pim\Services\CertificateService\CertificateService;

/**
 * Class CreateOrderReturnDto
 * Создание экземпляра класса dto OrderReturnDto для заказа, доставки и отправления
 *
 * @package App\Services\Dto\In\OrderReturn
 */
class OrderReturnDtoBuilder
{
    /**
     * Создание dto возврата заказа
     */
    public function buildFromOrder(Order $order): OrderReturnDto
    {
        $orderReturnDto = $this->buildBase($order->id, collect());
        $orderReturnDto->price = $order->delivery_price;
        $orderReturnDto->is_delivery = true;

        return $orderReturnDto;
    }

    /**
     * Создание dto возврата всего заказа сертификата
     */
    public function buildFromOrderAllCertificates(Order $order): OrderReturnDto
    {
        $certificates = $this->getCertificates($order->id);

        $basketItem = $order->basket->items->first();
        $certificatesBasketItems = collect();
        $certificates
            ->filter(fn($certificate) => in_array($certificate->status, [
                CertificateStatusDto::STATUS_IN_USE,
                CertificateStatusDto::STATUS_ACTIVATED,
                CertificateStatusDto::STATUS_PAID,
                CertificateStatusDto::STATUS_SENT,
            ], true))
            ->each(static function (CertificateDto $certificate) use (&$certificatesBasketItems, $basketItem) {
                $certificateBasketItem = clone $basketItem;
                $certificateBasketItem->qty = 1;
                $certificateBasketItem->price = in_array($certificate->status, [
                    CertificateStatusDto::STATUS_ACTIVATED,
                    CertificateStatusDto::STATUS_PAID,
                    CertificateStatusDto::STATUS_SENT,
                ], true) ? $certificate->price : $certificate->balance;
                $certificateBasketItem->cost = $certificateBasketItem->price;
                $certificatesBasketItems->push($certificateBasketItem);
            });

        $orderReturnDto = $this->buildBase($order->id, $certificatesBasketItems);
        $orderReturnDto->is_delivery = false;

        return $orderReturnDto;
    }

    /**
     * Создание dto возврата одного сертификата
     */
    public function buildFromOrderEachCertificate(Order $order, int $sum): OrderReturnDto
    {
        $basketItem = $order->basket->items->first();

        $certificateBasketItem = clone $basketItem;
        $certificateBasketItem->qty = 1;
        $certificateBasketItem->price = $sum;
        $certificateBasketItem->cost = $sum;

        $orderReturnDto = $this->buildBase($order->id, collect($certificateBasketItem));
        $orderReturnDto->is_delivery = false;

        return $orderReturnDto;
    }

    /**
     * Создание dto возврата отправления
     */
    public function buildFromShipment(Shipment $shipment): OrderReturnDto
    {
        $orderReturnDto = $this->buildBase($shipment->delivery->order_id, $shipment->basketItems);
        $orderReturnDto->is_delivery = false;

        return $orderReturnDto;
    }

    /**
     * Формирование базового объекта возврата заказа
     */
    protected function buildBase(int $orderId, Collection $basketItems): OrderReturnDto
    {
        $orderReturnDto = new OrderReturnDto();
        $orderReturnDto->order_id = $orderId;
        $orderReturnDto->status = OrderReturn::STATUS_CREATED;

        $orderReturnDto->items = $basketItems->transform(static function (BasketItem $item) {
            $orderReturnItemDto = new OrderReturnItemDto();
            $orderReturnItemDto->basket_item_id = $item->id;
            $orderReturnItemDto->qty = $item->qty;
            $orderReturnItemDto->ticket_ids = $item->getTicketIds();
            $orderReturnItemDto->price = $item->price / $item->qty;

            return $orderReturnItemDto;
        });

        return $orderReturnDto;
    }

    private function getCertificates(int $orderId): Collection
    {
        /** @var CertificateService $certificateService */
        $certificateService = resolve(CertificateService::class);
        $query = $certificateService
            ->requestQuery()
            ->orderId($orderId)
            ->withCertificates();

        return $query->requests()->first()->certificates;
    }
}
