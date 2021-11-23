<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Models\Payment\PaymentStatus;
use App\Services\Dto\In\OrderReturn\OrderReturnDto;
use App\Services\Dto\In\OrderReturn\OrderReturnItemDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MerchantManagement\Services\MerchantService\MerchantService;

/**
 * Class OrderReturnService
 * @package App\Services
 */
class OrderReturnService
{
    /**
     * Создать возврат по заказу
     * @throws \Exception
     */
    public function create(OrderReturnDto $orderReturnDto): ?OrderReturn
    {
        $order = Order::findOrFail($orderReturnDto->order_id)->load('basket.items');

        $basketItemIds = $orderReturnDto->items->pluck('basket_item_id');

        if (!$this->needCreateOrderReturn($order, $orderReturnDto, $basketItemIds)) {
            return null;
        }

        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::whereKey($basketItemIds)->with('shipmentItem.shipment')->get()->keyBy('id');

        $orderReturn = DB::transaction(function () use ($orderReturnDto, $order, $basketItems) {
            $orderReturn = $this->createOrderReturn($order, $orderReturnDto);

            foreach ($orderReturnDto->items as $item) {
                $basketItem = $basketItems->get($item->basket_item_id);
                if (!$basketItem) {
                    throw new \Exception("BasketItem by id={$item->basket_item_id} not found");
                }

                $this->createOrderReturnItem($order, $orderReturn, $basketItem, $item);
            }

            $this->calcOrderReturnPrice($orderReturn, $orderReturnDto->price);

            return $orderReturn;
        });

        rescue(fn() => $this->returnBillingOperations($basketItems, $order, $orderReturn));

        return $orderReturn;
    }

    private function needCreateOrderReturn(
        Order $order,
        OrderReturnDto $orderReturnDto,
        Collection $basketItemIds
    ): bool {
        if (!in_array((int) $order->payment_status, [PaymentStatus::PAID, PaymentStatus::HOLD])) {
            return false;
        }

        //TODO Предусмотреть в дальнейшем условие возврата неполного количества одного товара
        $existOrderReturnItems = OrderReturnItem::query()
            ->whereIn('basket_item_id', $basketItemIds)
            ->exists();

        if ($existOrderReturnItems) {
            return false;
        }

        if ($orderReturnDto->is_delivery) {
            $existOrderReturnDelivery = OrderReturn::query()
                ->where('order_id', $order->id)
                ->where('is_delivery', true)
                ->exists();

            if ($existOrderReturnDelivery) {
                return false;
            }
        }

        return true;
    }

    private function createOrderReturn(Order $order, OrderReturnDto $orderReturnDto): OrderReturn
    {
        $orderReturn = new OrderReturn();
        $orderReturn->order_id = $order->id;
        $orderReturn->customer_id = $order->customer_id;
        $orderReturn->type = $order->type;
        $orderReturn->number = OrderReturn::makeNumber($order->id);
        $orderReturn->status = $orderReturnDto->status;
        $orderReturn->price = 0;
        $orderReturn->is_delivery = $orderReturnDto->is_delivery;
        $orderReturn->save();

        return $orderReturn;
    }

    private function createOrderReturnItem(
        Order $order,
        OrderReturn $orderReturn,
        BasketItem $basketItem,
        OrderReturnItemDto $item
    ): OrderReturnItem {
        $orderReturnItem = new OrderReturnItem();
        $orderReturnItem->order_return_id = $orderReturn->id;
        $orderReturnItem->basket_item_id = $basketItem->id;
        $orderReturnItem->offer_id = $basketItem->offer_id;
        $orderReturnItem->referrer_id = $basketItem->referrer_id;
        $orderReturnItem->bundle_id = $basketItem->bundle_id;
        $orderReturnItem->type = $basketItem->type;

        if ($order->type == Basket::TYPE_MASTER) {
            /**
             * Проверяем, что указаны id билетов для возврата
             */
            if (!$item->ticket_ids) {
                throw new \Exception("Returning ticket_ids for BasketItem with id={$basketItem->id} not specified");
            }
            /**
             * Проверяем, что id билетов для возврата указаны у BasketItem корзины заказа
             */
            if ($item->ticket_ids != array_intersect($basketItem->getTicketIds(), $item->ticket_ids)) {
                throw new \Exception("Returning ticket_ids for BasketItem with id={$basketItem->id} not contained at order");
            }
            $basketItem->setTicketIds($item->ticket_ids);
        }
        $orderReturnItem->product = $basketItem->product;
        $orderReturnItem->name = $basketItem->name;
        $orderReturnItem->qty = $order->type == Basket::TYPE_MASTER ? count($item->ticket_ids) : $item->qty;
        /**
         * Проверяем, что кол-во возвращаемого товара не больше, чем в корзине
         */
        if ($orderReturnItem->qty > $basketItem->qty) {
            throw new \Exception("Returning qty for BasketItem with id={$basketItem->id} more than at order");
        }
        $orderReturnItem->price = $item->price ?: $basketItem->price / $basketItem->qty * $orderReturnItem->qty;
        $orderReturnItem->commission = 0; //todo Доделать расчет суммы удержанной комиссии
        $orderReturnItem->save();

        return $orderReturnItem;
    }

    private function calcOrderReturnPrice(OrderReturn $orderReturn, ?int $price = null): void
    {
        if ($price) {
            $orderReturn->price = $price;
        } else {
            $orderReturn->priceRecalc(false);
        }
        $orderReturn->commissionRecalc();
        $orderReturn->save();
    }

    private function returnBillingOperations(Collection $basketItems, Order $order, OrderReturn $orderReturn): void
    {
        $billingListByMerchants = $this->getMerchantBillings($basketItems, $order);

        foreach ($orderReturn->items as $orderReturnItem) {
            if (!isset($billingListByMerchants[$orderReturnItem->offer_id])) {
                continue;
            }

            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);
            $merchantService->addReturn(
                $billingListByMerchants[$orderReturnItem->offer_id]['merchant_id'],
                $billingListByMerchants[$orderReturnItem->offer_id]['id']
            );
        }
    }

    /**
     * Получить записи биллинга элементов корзины
     */
    private function getMerchantBillings(Collection $basketItems, Order $order): array
    {
        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $basketItemsByMerchants = [];
        $billingListByMerchants = [];
        foreach ($basketItems as $basketItem) {
            if ($basketItem->shipmentItem->shipment->merchant_id) {
                $basketItemsByMerchants[$basketItem->shipmentItem->shipment->merchant_id][] = $basketItem;
            }
        }

        foreach ($basketItemsByMerchants as $merchantId => $merchantBasketItems) {
            $restQuery = (new RestQuery())
                ->setFilter('merchant_id', $merchantId)
                ->setFilter('offer_id', array_column($merchantBasketItems, 'offer_id'))
                ->setFilter('order_id', $order->id);
            $billingList = $merchantService->merchantBillingList($restQuery, $merchantId);

            if (!isset($billingList['items'])) {
                continue;
            }

            foreach ($billingList['items'] as $billingItem) {
                $billingListByMerchants[$billingItem['offer_id']] = [
                    'id' => $billingItem['id'],
                    'merchant_id' => $merchantId,
                ];
            }
        }

        return $billingListByMerchants;
    }
}
