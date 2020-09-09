<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Pim\Services\SearchService\SearchService;

/**
 * Class BasketItemObserver
 * @package App\Observers\Basket
 * @todo не забыть про пересчёт корзины при изменении количества товара
 */
class BasketItemObserver
{
    /**
     * Handle the basket item "saving" event.
     * @param  BasketItem $basketItem
     */
    public function saving(BasketItem $basketItem)
    {
        /*if ($basketItem->qty != $basketItem->getOriginal('qty') ||
            $basketItem->price != $basketItem->getOriginal('price') ||
            $basketItem->discount != $basketItem->getOriginal('discount')
        ) {
            $basketItem->costRecalc(false);
        }*/
    }

    /**
     * Handle the basket item "saved" event.
     * @param  BasketItem $basketItem
     */
    public function saved(BasketItem $basketItem)
    {
        /*if ($basketItem->basket->order) {
            $basketItem->basket->order->costRecalc();
        }*/

        if ($basketItem->qty != $basketItem->getOriginal('qty')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->recalc();
            }
        }

        if ($basketItem->qty != $basketItem->getOriginal('qty') ||
            $basketItem->price != $basketItem->getOriginal('price')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->costRecalc();
            }
        }
    }

    /**
     * Handle the basket item "created" event.
     * @param  BasketItem $basketItem
     */
    public function created(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_CREATE, $basketItem->basket->order, $basketItem);
        }

        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $searchService->markProductForIndexViaOffer($basketItem->offer_id);

        /** @var Order */
        $order = $basketItem->basket->order;
        if($order) {
            app(ServiceNotificationService::class)->send($order->getUser()->id, 'servisnyeizmenenie_zakaza_sostav_zakaza', [
                'ORDER_ID' => $order->id,
                'CUSTOMER_NAME' => $order->getUser()->first_name,
                'LINK_ORDER' => sprintf("%s/profile/orders/%d", config('app.showcase_host'), $order->id),
                'NAME_GOODS' => $basketItem->name,
                'PART_PRICE' => number_format($basketItem->cost, 2),
                'NUMBER' => (int) $basketItem->qty,
                'DELIVERY_PRICE' => number_format($basketItem->shipmentItem->shipment->cost, 2),
                'TOTAL_PRICE' => number_format($order->cost, 2),
                'REFUND_ORDER' => number_format($basketItem->cost, 2),
            ]);
        }
    }

    /**
     * Handle the basket item "updated" event.
     * @param  BasketItem $basketItem
     */
    public function updated(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_UPDATE, $basketItem->basket->order, $basketItem);
        }
    }

    /**
     * Handle the basket item "deleting" event.
     * @param  BasketItem $basketItem
     * @throws \Exception
     */
    public function deleting(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_DELETE, $basketItem->basket->order, $basketItem);
        }

        if ($basketItem->shipmentItem) {
            $basketItem->shipmentItem->delete();
        }

        if ($basketItem->shipmentPackageItem) {
            $basketItem->shipmentPackageItem->delete();
        }

        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $searchService->markProductForIndexViaOffer($basketItem->offer_id);

        $order = $basketItem->basket->order;
        if($order) {
            app(ServiceNotificationService::class)->send($order->getUser()->id, 'servisnyeizmenenie_zakaza_sostav_zakaza', [
                'ORDER_ID' => $order->id,
                'LINK_ORDER' => sprintf("%s/profile/orders/%d", config('app.showcase_host'), $order->id)
            ]);
        }
    }
}
