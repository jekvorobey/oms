<?php

namespace App\Services\Dto\In\OrderReturn;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use Illuminate\Support\Collection;

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

            return $orderReturnItemDto;
        });

        return $orderReturnDto;
    }
}
