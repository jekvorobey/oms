<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Services\DeliveryService;
use Exception;
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
     */
    public function saving(BasketItem $basketItem)
    {
        if (
            $basketItem->qty != $basketItem->getOriginal('qty') ||
            $basketItem->qty_canceled != $basketItem->getOriginal('qty_canceled')
        ) {
            $basketItem->priceRecalc(false);
        }
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
        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $searchService->markProductForIndexViaOffer($basketItem->offer_id);
    }

    /**
     * Handle the basket item "deleting" event.
     * @throws Exception
     */
    public function deleting(BasketItem $basketItem)
    {
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

    /**
     * Handle the basket item "updated" event.
     * @return void
     * @throws Exception
     */
    public function updated(BasketItem $basketItem)
    {
        $this->setIsCanceledToShipment($basketItem);
    }

    /**
     * Автоматическая установка флага отмены для отправления, если все её товары отменены
     * @throws Exception
     */
    protected function setIsCanceledToShipment(BasketItem $basketItem): void
    {
        if ($basketItem->wasChanged('is_canceled') && $basketItem->is_canceled) {
            /** @var Shipment $shipment */
            $shipment = Shipment::find($basketItem->shipmentItem->shipment_id);
            if ($shipment->is_canceled) {
                return;
            }

            $allBasketItemsCanceled = true;
            foreach ($shipment->basketItems as $shipmentBasketItem) {
                if (!$shipmentBasketItem->isCanceled()) {
                    $allBasketItemsCanceled = false;
                    break;
                }
            }

            if ($allBasketItemsCanceled) {
                /** @var DeliveryService $deliveryService */
                $deliveryService = resolve(DeliveryService::class);
                $deliveryService->cancelShipment($shipment, $basketItem->return_reason_id);
            }
        }
    }
}
