<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\WithHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     description="Состав отправления (набор товаров с одного склада одного мерчанта)",
 *     @OA\Property(property="shipment_id", type="integer", description="ID отгрузки"),
 *     @OA\Property(property="basket_item_id", type="integer", description="ID товара в корзине"),
 *     @OA\Property(property="shipment", type="array", @OA\Items(ref="#/components/schemas/Shipment")),
 *     @OA\Property(property="basketItem", type="array", @OA\Items(ref="#/components/schemas/BasketItem")),
 * )
 *
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
class ShipmentItem extends Model
{
    use WithHistory;

    /** @var string */
    protected $table = 'shipment_items';

    /** @var bool */
    protected static $unguarded = true;

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

    protected function historyMainModel(): array
    {
        return [$this->shipment->delivery->order, $this->shipment];
    }
}
