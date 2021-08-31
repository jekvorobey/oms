<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\Dto\In\OrderReturn\OrderReturnDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class OrderReturnService
 * @package App\Services
 */
class OrderReturnService
{
    /**
     * Создать возврат по заказу
     * @return array - [id, number]
     * @throws \Exception
     */
    public function createOrderReturn(OrderReturnDto $orderReturnDto): array
    {
        /** @var Order $order */
        $order = Order::query()->where('id', $orderReturnDto->order_id)->with('basket.items')->get()->first();
        if (!$order) {
            throw new \Exception("Order by id={$orderReturnDto->order_id} not found");
        }

        return DB::transaction(function () use ($orderReturnDto, $order) {
            $orderReturn = new OrderReturn();
            $orderReturn->order_id = $order->id;
            $orderReturn->customer_id = $order->customer_id;
            $orderReturn->type = $order->type;
            $orderReturn->number = OrderReturn::makeNumber($order->id);
            $orderReturn->status = $orderReturnDto->status;
            $orderReturn->save();

            $basketItemIds = $orderReturnDto->items->pluck('basket_item_id');
            /** @var Collection|BasketItem[] $basketItems */
            $basketItems = BasketItem::query()->whereIn('id', $basketItemIds)->get()->keyBy('id');

            foreach ($orderReturnDto->items as $item) {
                $orderReturnItem = new OrderReturnItem();
                $orderReturnItem->order_return_id = $orderReturn->id;
                $orderReturnItem->basket_item_id = $item->basket_item_id;
                if (!$basketItems->has($item->basket_item_id)) {
                    throw new \Exception("BasketItem by id={$item->basket_item_id} not found");
                }
                $basketItem = $basketItems[$item->basket_item_id];
                $orderReturnItem->offer_id = $basketItem->offer_id;
                $orderReturnItem->referrer_id = $basketItem->referrer_id;
                $orderReturnItem->bundle_id = $basketItem->bundle_id;
                $orderReturnItem->type = $basketItem->type;
                if ($order->type == Basket::TYPE_MASTER) {
                    /**
                     * Проверяем, что указаны id билетов для возврата
                     */
                    if (!$item->ticket_ids) {
                        throw new \Exception("Returning ticket_ids for BasketItem with id={$item->basket_item_id} not specified");
                    }
                    /**
                     * Проверяем, что id билетов для возврата указаны у BasketItem корзины заказа
                     */
                    if ($item->ticket_ids != array_intersect($basketItem->getTicketIds(), $item->ticket_ids)) {
                        throw new \Exception("Returning ticket_ids for BasketItem with id={$item->basket_item_id} not contained at order");
                    }
                    $basketItem->setTicketIds($item->ticket_ids);
                }
                $orderReturnItem->product = $basketItem->product;
                $orderReturnItem->name = $basketItem->name;
                $orderReturnItem->qty = $order->type == Basket::TYPE_PRODUCT ? $item->qty : count($item->ticket_ids);
                /**
                 * Проверяем, что кол-во возвращаемого товара не больше, чем в корзине
                 */
                if ($orderReturnItem->qty > $basketItem->qty) {
                    throw new \Exception("Returning qty for BasketItem with id={$item->basket_item_id} more than at order");
                }
                $orderReturnItem->price = $basketItem->price / $basketItem->qty * $orderReturnItem->qty;
                $orderReturnItem->commission = 0; //todo Доделать расчет суммы удержанной комиссии
                $orderReturnItem->save();
            }

            $orderReturn->priceRecalc(false);
            $orderReturn->commissionRecalc();

            return [$orderReturn->id, $orderReturn->number];
        });
    }

    public function updateOrderReturn(int $id, OrderReturnDto $orderReturnDto): void
    {
        $query = OrderReturn::query()->where('id', $id);
        if ($orderReturnDto->items->isNotEmpty()) {
            $query->with('items');
        }
        /** @var OrderReturn $orderReturn */
        $orderReturn = $query->get();
        if (!$orderReturn) {
            throw new \Exception("OrderReturn by id={$id} not found");
        }

        DB::transaction(function () use ($orderReturnDto, $orderReturn) {
            $orderReturn->status = $orderReturnDto->status;
            $orderReturn->save();

            if ($orderReturnDto->items->isNotEmpty()) {
                $basketItemIds = $orderReturnDto->items->pluck('basket_item_id');
                $orderReturnItems = $orderReturnDto->items->keyBy('id');
                /**
                 * Удаляем элементы возврата, которых больше нет
                 */
                $deletedItems = $orderReturn->items->filter(function (BasketItem $item) use ($basketItemIds) {
                    return !$basketItemIds->contains($item->id);
                });
                foreach ($deletedItems as $item) {
                    $item->delete();
                    $orderReturnItems->forget($item->id);
                }

                /**
                 * Обновляем/создаем новые элементы возврата
                 */
                foreach ($orderReturnDto->items as $itemDto) {
                    if ($orderReturnItems->has($itemDto->basket_item_id)) {
                        // $orderReturnItem = $orderReturnItems[$itemDto->basket_item_id];
                        //todo
                    }
                }
            }
        });
    }
}
