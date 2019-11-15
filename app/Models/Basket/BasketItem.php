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
 * @property int $offer_id - id предложения мерчанта
 * @property int $type - тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 * @property string $name - название товара
 * @property float $qty - кол-во
 * @property float|null $price - цена за единицу товара без учета скидки
 * @property float|null $discount - скидка за все кол-во товара
 * @property float|null $cost - сумма за все кол-во товара с учетом скидки (расчитывается автоматически)
 * @property array $product - данные зависящие от типа товара
 *
 * @property-read Basket $basket
 * @property-read ShipmentItem $shipmentItem
 * @property-read ShipmentPackageItem $shipmentPackageItem
 *
 * @OA\Schema(
 *     schema="BasketItem",
 *     @OA\Property(property="id", type="integer", description="id оффера в корзине"),
 *     @OA\Property(property="basket_id", type="integer", description="id корзины"),
 *     @OA\Property(property="offer_id", type="integer", description="id предложения мерчанта"),
 *     @OA\Property(property="name", type="string", description="название товара"),
 *     @OA\Property(property="qty", type="integer", description="кол-во"),
 *     @OA\Property(property="price", type="number", description="цена за единицу товара без учета скидки"),
 *     @OA\Property(property="discount", type="number", description="скидка за все кол-во товара"),
 *     @OA\Property(property="cost", type="number", description="сумма за все кол-во товара с учетом скидки (расчитывается автоматически)"),
 * )
 */
class BasketItem extends OmsModel
{
    protected $casts = [
        'product' => 'array'
    ];
    
    public function __construct(array $attributes = [])
    {
        $this->product = [];
        parent::__construct($attributes);
    }
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
