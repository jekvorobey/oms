<?php

namespace App\Services\Dto\In\OrderReturn;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;

/**
 * Class CreateOrderReturnDto
 * Создание экземпляра класса dto OrderReturnDto для заказа, доставки и отправления
 *
 * @package App\Services\Dto\In\OrderReturn
 */
class CreateOrderReturnDto
{
    /**
     * Создание dto возврата заказа
     *
     * @param Order $order
     * @return OrderReturnDto
     */
    public function getFromOrder(Order $order): OrderReturnDto
    {
        $orderReturnDto = new OrderReturnDto();
        $orderReturnDto->order_id = $order->id;
        $orderReturnDto->status = OrderReturn::STATUS_CREATED;
        $orderReturnDto->items = collect($order->basket->items->transform(static function (BasketItem $item) {
            $orderReturnItemDto = new OrderReturnItemDto();
            $orderReturnItemDto->basket_item_id = $item->id;
            $orderReturnItemDto->qty = $item->qty;
            $orderReturnItemDto->ticket_ids = $item->getTicketIds();

            return $orderReturnItemDto;
        }));

        return $orderReturnDto;
    }

    /**
     * Создание dto возврата доставки
     *
     * @param Delivery $delivery
     * @return OrderReturnDto
     */
    public function getFromDelivery(Delivery $delivery): OrderReturnDto
    {
        $orderReturnDto = new OrderReturnDto();
        $orderReturnDto->order_id = $delivery->order_id;
        $orderReturnDto->status = OrderReturn::STATUS_CREATED;
        $orderReturnDto->items = collect($delivery->shipments->basketItems->transform(static function (BasketItem $item) {
            $orderReturnItemDto = new OrderReturnItemDto();
            $orderReturnItemDto->basket_item_id = $item->id;
            $orderReturnItemDto->qty = $item->qty;
            $orderReturnItemDto->ticket_ids = $item->getTicketIds();

            return $orderReturnItemDto;
        }));

        return $orderReturnDto;
    }

    /**
     * Создание dto возврата отправления
     *
     * @param Shipment $shipment
     * @return OrderReturnDto
     */
    public function getFromShipment(Shipment $shipment): OrderReturnDto
    {
        $orderReturnDto = new OrderReturnDto();
        $orderReturnDto->order_id = $shipment->delivery->order_id;
        $orderReturnDto->status = OrderReturn::STATUS_CREATED;
        $orderReturnDto->items = collect($shipment->basketItems->transform(static function (BasketItem $item) {
            $orderReturnItemDto = new OrderReturnItemDto();
            $orderReturnItemDto->basket_item_id = $item->id;
            $orderReturnItemDto->qty = $item->qty;
            $orderReturnItemDto->ticket_ids = $item->getTicketIds();

            return $orderReturnItemDto;
        }));

        return $orderReturnDto;
    }
}
