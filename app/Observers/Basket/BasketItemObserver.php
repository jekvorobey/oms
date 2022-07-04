<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Services\Dto\In\OrderReturn\OrderReturnDtoBuilder;
use App\Services\OrderReturnService;
use App\Services\ShipmentService;
use Exception;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Pim\Services\SearchService\SearchService;

/**
 * Class BasketItemObserver
 * @package App\Observers\Basket
 * @todo не забыть про пересчёт корзины при изменении количества товара
 */
class BasketItemObserver
{
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
     * Handle the basket item "saved" event.
     */
    public function saved(BasketItem $basketItem)
    {
        /*if ($basketItem->basket->order) {
            $basketItem->basket->order->costRecalc();
        }*/
        $this->recalcShipment($basketItem);
        $this->returnBonuses($basketItem);
    }

    /**
     * Handle the basket item "updating" event.
     */
    public function updating(BasketItem $basketItem)
    {
        $this->pricesRecalc($basketItem);
    }

    /**
     * Handle the basket item "updated" event.
     * @return void
     * @throws Exception
     */
    public function updated(BasketItem $basketItem)
    {
        $this->createOrderReturn($basketItem);
        $this->setIsCanceledToShipment($basketItem);
        $this->setOrderIsPartiallyCancelled($basketItem);
        $this->syncShipmentPackageItemWhenCancelled($basketItem);
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
     * Создать возврат по заказу
     */
    private function createOrderReturn(BasketItem $basketItem): void
    {
        if ($basketItem->wasChanged('qty') && $basketItem->wasChanged('qty_canceled')) {
            $qtyToReturn = $basketItem->getOriginal('qty') - $basketItem->qty;
            $priceToReturn = $basketItem->getOriginal('price') - $basketItem->price;
            $basketItemReturnDto = (new OrderReturnDtoBuilder())
                ->buildFromCancelBasketItem($basketItem->basket->order, $basketItem, $qtyToReturn, $priceToReturn);
            /** @var OrderReturnService $orderReturnService */
            $orderReturnService = resolve(OrderReturnService::class);
            rescue(fn() => $orderReturnService->create($basketItemReturnDto));
        }
    }

    /**
     * Установка заказу флага частичной отмены
     */
    private function setOrderIsPartiallyCancelled(BasketItem $basketItem): void
    {
        if ($basketItem->qty_canceled && $basketItem->wasChanged('qty_canceled')) {
            $order = $basketItem->basket->order;
            if (!$order->is_canceled) {
                $order->is_partially_cancelled = true;
                $order->save();
            }
        }
    }

    /**
     * Автоматическая установка флага отмены для отправления, если все её товары отменены
     * @throws Exception
     */
    private function setIsCanceledToShipment(BasketItem $basketItem): void
    {
        if ($basketItem->is_canceled && $basketItem->wasChanged('is_canceled')) {
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
                /** @var ShipmentService $shipmentService */
                $shipmentService = resolve(ShipmentService::class);
                $shipmentService->cancelShipment($shipment, $basketItem->return_reason_id);
            }
        }
    }

    private function syncShipmentPackageItemWhenCancelled(BasketItem $basketItem): void
    {
        if (
            $basketItem->qty_canceled
            && $basketItem->wasChanged('qty_canceled')
            && $shipmentPackageItem = $basketItem->shipmentPackageItem
        ) {
            $cancelledQty = $basketItem->getOriginal('qty') - $basketItem->qty;
            $restPackageItemQty = $shipmentPackageItem->qty - $cancelledQty;

            if ($restPackageItemQty > 0) {
                $shipmentPackageItem->update(['qty' => $restPackageItemQty]);
            } else {
                $shipmentPackageItem->delete();
            }
        }
    }

    private function returnBonuses(BasketItem $basketItem): void
    {
        if ($basketItem->qty_canceled && $basketItem->wasChanged('bonus_spent')) {
            $spent = $basketItem->getOriginal('bonus_spent') - $basketItem->bonus_spent;
            $order = $basketItem->basket->order;
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $customerService->returnDebitingBonus($order->customer_id, $order->id, $spent);
        }
    }

    private function pricesRecalc(BasketItem $basketItem): void
    {
        if ($basketItem->isDirty('qty')) {
            $basketItem->pricesRecalc(false);
        }
    }

    private function recalcShipment(BasketItem $basketItem): void
    {
        if ($basketItem->shipmentItem && $basketItem->wasChanged('qty')) {
            $shipment = $basketItem->shipmentItem->shipment;
            $shipment->load('basketItems');

            $shipment->recalc();
            $shipment->costRecalc();
        }
    }
}
