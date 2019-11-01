<?php

namespace App\Models\Basket;

use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use App\Models\History\History;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Состав корзины
 * Class BasketItem
 * @package App\Models
 *
 * @property int $basket_id - id корзины
 * @property int $store_id - id склада
 * @property int $offer_id - id предложения мерчанта
 * @property string $name - название товара
 * @property float $qty - кол-во
 * @property float|null $price - цена за единицу товара без учета скидки
 * @property float|null $discount - скидка за все кол-во товара
 * @property float|null $cost - сумма за все кол-во товара с учетом скидки
 *
 * @property-read Basket $basket
 * @property-read ShipmentItem $shipmentItem
 * @property-read ShipmentPackageItem $shipmentPackageItem
 *
 * @OA\Schema(
 *     schema="BasketItem",
 *     @OA\Property(property="id", type="integer", description="id оффера в корзине"),
 *     @OA\Property(property="basket_id", type="integer", description="id корзины"),
 *     @OA\Property(property="store_id", type="integer", description="id склада"),
 *     @OA\Property(property="offer_id", type="integer", description="id предложения мерчанта"),
 *     @OA\Property(property="name", type="string", description="название товара"),
 *     @OA\Property(property="qty", type="integer", description="кол-во"),
 *     @OA\Property(property="price", type="number", description="цена за единицу товара без учета скидки"),
 *     @OA\Property(property="discount", type="number", description="скидка за все кол-во товара"),
 *     @OA\Property(property="cost", type="number", description="сумма за все кол-во товара с учетом скидки"),
 * )
 */
class BasketItem extends OmsModel
{
    /** @var bool */
    protected static $unguarded = true;
    
    /**
     * @return BelongsTo
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }
    
    /**
     * @return HasOne
     */
    public function shipmentItem(): HasOne
    {
        return $this->hasOne(ShipmentItem::class);
    }
    
    /**
     * @return HasOne
     */
    public function shipmentPackageItem(): HasOne
    {
        return $this->hasOne(ShipmentPackageItem::class);
    }
    
    /**
     * Пересчитать сумму позиции корзины
     * @param bool $save
     */
    public function costRecalc(bool $save = true): void
    {
        $this->cost = $this->qty * $this->price - $this->discount;
        if ($save) {
            $this->save();
        }
    }
    
    protected static function boot()
    {
        parent::boot();
    
        self::saving(function (self $basketItem) {
            if ($basketItem->qty != $basketItem->getOriginal('qty') ||
                $basketItem->price != $basketItem->getOriginal('price') ||
                $basketItem->discount != $basketItem->getOriginal('discount')
            ) {
                $basketItem->costRecalc(false);
            }
        });
        
        self::saved(function (self $basketItem) {
            if ($basketItem->basket->order) {
                $basketItem->basket->order->costRecalc();
            }
        });
        
        self::created(function (self $basketItem) {
            if ($basketItem->basket->order && $basketItem->basket->order->id) {
                History::saveEvent(HistoryType::TYPE_CREATE, $basketItem->basket->order, $basketItem);
            }
        });
    
        self::updated(function (self $basketItem) {
            if ($basketItem->basket->order && $basketItem->basket->order->id) {
                History::saveEvent(HistoryType::TYPE_UPDATE, $basketItem->basket->order, $basketItem);
            }
        });
    
        self::deleting(function (self $basketItem) {
            if ($basketItem->basket->order && $basketItem->basket->order->id) {
                History::saveEvent(HistoryType::TYPE_DELETE, $basketItem->basket->order, $basketItem);
            }
            
            if ($basketItem->shipmentItem) {
                $basketItem->shipmentItem->delete();
            }
            
            if ($basketItem->shipmentPackageItem) {
                $basketItem->shipmentPackageItem->delete();
            }
        });
    }
}
