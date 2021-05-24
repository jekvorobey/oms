<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\History\History;
use App\Models\History\HistoryType;
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
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
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
     */
    public function saved(BasketItem $basketItem)
    {
        /*if ($basketItem->basket->order) {
            $basketItem->basket->order->costRecalc();
        }*/

        if (
            $basketItem->qty != $basketItem->getOriginal('qty')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->recalc();
            }
        }

        if (
            $basketItem->qty != $basketItem->getOriginal('qty') ||
            $basketItem->price != $basketItem->getOriginal('price')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->costRecalc();
            }
        }
    }

    /**
     * Handle the basket item "created" event.
     */
    public function created(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_CREATE, $basketItem->basket->order, $basketItem);
        }

        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $searchService->markProductForIndexViaOffer($basketItem->offer_id);
    }

    /**
     * Handle the basket item "updated" event.
     */
    public function updated(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_UPDATE, $basketItem->basket->order, $basketItem);
        }
    }

    /**
     * Handle the basket item "deleting" event.
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
    }
}
