<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     description="Содержимое коробки отправления",
 *     @OA\Property(property="shipment_package_id", type="integer", description="id посылки"),
 *     @OA\Property(property="basket_item_id", type="integer", description="id корзины"),
 *     @OA\Property(property="qty", type="number", description="количество"),
 *     @OA\Property(property="set_by", type="integer", description=""),
 *     @OA\Property(property="shipmentPackage", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackage")),
 *     @OA\Property(property="basketItem", type="array", @OA\Items(ref="#/components/schemas/BasketItem")),
 * )
 *
 * Содержимое коробки отправления
 * Class ShipmentPackageItem
 * @package App\Models\Delivery
 *
 * @property int $shipment_package_id
 * @property int $basket_item_id
 * @property float $qty
 * @property int $set_by
 *
 * @property-read ShipmentPackage $shipmentPackage
 * @property-read BasketItem $basketItem
 */
class ShipmentPackageItem extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'shipment_package_id',
        'basket_item_id',
        'qty',
        'set_by',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'shipment_package_items';

    /** @var array */
    protected static $restIncludes = ['shipmentPackage', 'basketItem'];

    public function shipmentPackage(): BelongsTo
    {
        return $this->belongsTo(ShipmentPackage::class);
    }

    public function basketItem(): BelongsTo
    {
        return $this->belongsTo(BasketItem::class);
    }
}
