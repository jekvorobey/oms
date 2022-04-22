<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Services\DeliveryService;
use App\Services\Dto\In\OrderReturn\OrderReturnDtoBuilder;
use App\Services\OrderReturnService;
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
     * Handle the basket item "saved" event.
     */
    public function saved(BasketItem $basketItem)
    {
        /*if ($basketItem->basket->order) {
            $basketItem->basket->order->costRecalc();
        }*/
        $this->recalcWeightAndSizes($basketItem);
        $this->costRecalc($basketItem);
        $this->returnBonuses($basketItem);
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
    }

    /**
     * Создать возврат по заказу
     */
    private function createOrderReturn(BasketItem $basketItem): void
    {
        if ($basketItem->wasChanged('qty') && $basketItem->wasChanged('qty_cancelled')) {
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
     * Автоматическая установка флага отмены для отправления, если все её товары отменены
     * @throws Exception
     */
    private function setIsCanceledToShipment(BasketItem $basketItem): void
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

    private function pricesRecalc(BasketItem $basketItem): void
    {
        if (
            $basketItem->qty != $basketItem->getOriginal('qty')
        ) {
            $basketItem->pricesRecalc(false);
        }
    }

    private function returnBonuses(BasketItem $basketItem): void
    {
        if ($basketItem->bonus_spent != $basketItem->getOriginal('bonus_spent')) {
            $spent = $basketItem->getOriginal('bonus_spent') - $basketItem->bonus_spent;
            $order = $basketItem->basket->order;
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $customerService->returnDebitingBonus($order->customer_id, $order->id, $spent);
        }
    }

    private function recalcWeightAndSizes(BasketItem $basketItem): void
    {
        if (
            $basketItem->qty != $basketItem->getOriginal('qty')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->recalc();
            }
        }
    }

    private function costRecalc(BasketItem $basketItem): void
    {
        if (
            $basketItem->qty != $basketItem->getOriginal('qty') ||
            $basketItem->price != $basketItem->getOriginal('price')
        ) {
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->shipment->costRecalc();
            }
        }
    }
}
