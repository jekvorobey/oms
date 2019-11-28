<?php

namespace App\Observers\Basket;

use App\Models\Basket\BasketItem;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class BasketItemObserver
 * @package App\Observers\Basket
 * @todo не забыть про пересчёт корзины при изменении количества товара
 */
class BasketItemObserver
{
//    /**
//     * Handle the order "saving" event.
//     * @param  BasketItem $basketItem
//     */
//    public function saving(BasketItem $basketItem)
//    {
//        if ($basketItem->qty != $basketItem->getOriginal('qty') ||
//            $basketItem->price != $basketItem->getOriginal('price') ||
//            $basketItem->discount != $basketItem->getOriginal('discount')
//        ) {
//            $basketItem->costRecalc(false);
//        }
//    }

//    /**
//     * Handle the order "saved" event.
//     * @param  BasketItem $basketItem
//     */
//    public function saved(BasketItem $basketItem)
//    {
//        if ($basketItem->basket->order) {
//            $basketItem->basket->order->costRecalc();
//        }
//    }
    
    /**
     * Handle the order "created" event.
     * @param  BasketItem $basketItem
     */
    public function created(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_CREATE, $basketItem->basket->order, $basketItem);
        }
    }
    
    /**
     * Handle the order "updated" event.
     * @param  BasketItem $basketItem
     */
    public function updated(BasketItem $basketItem)
    {
        if ($basketItem->basket->order) {
            History::saveEvent(HistoryType::TYPE_UPDATE, $basketItem->basket->order, $basketItem);
        }
    }
    
    /**
     * Handle the order "deleting" event.
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
    }
}
