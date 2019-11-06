<?php

namespace App\Models\Basket;

use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\OmsModel;
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
 * @property float $weight - вес единицы товара
 * @property float $width - ширина единицы товара
 * @property float $height - высота единицы товара
 * @property float $length - длина единицы товара
 * @property float $qty - кол-во
 * @property float|null $price - цена за единицу товара без учета скидки
 * @property float|null $discount - скидка за все кол-во товара
 * @property float|null $cost - сумма за все кол-во товара с учетом скидки (расчитывается автоматически)
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
 *     @OA\Property(property="weight", type="number", description="вес единицы товара"),
 *     @OA\Property(property="width", type="number", description="ширина единицы товара"),
 *     @OA\Property(property="height", type="number", description="высота единицы товара"),
 *     @OA\Property(property="length", type="number", description="длина единицы товара"),
 *     @OA\Property(property="qty", type="integer", description="кол-во"),
 *     @OA\Property(property="price", type="number", description="цена за единицу товара без учета скидки"),
 *     @OA\Property(property="discount", type="number", description="скидка за все кол-во товара"),
 *     @OA\Property(property="cost", type="number", description="сумма за все кол-во товара с учетом скидки (расчитывается автоматически)"),
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
}
