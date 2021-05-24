<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Состав отправления (набор товаров с одного склада одного мерчанта)
 * Class ShipmentItem
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $basket_item_id
 *
 * @property-read Shipment $shipment
 * @property-read BasketItem $basketItem
 */
class ShipmentItem extends OmsModel
{
    /** @var string */
    protected $table = 'shipment_items';

    /** @var array */
    protected static $restIncludes = ['shipment', 'basketItem'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function basketItem(): BelongsTo
    {
        return $this->belongsTo(BasketItem::class);
    }
}
