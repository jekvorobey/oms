<?php

namespace App\Models\Basket;

use App\Models\OmsModel;
use App\Models\Order\OrderHistoryEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property float|null $price - цена за единицу товара
 *
 * @property-read Basket $basket
 *
 * @OA\Schema(
 *     schema="BasketItem",
 *     @OA\Property(property="id", type="integer", description="id оффера в корзине"),
 *     @OA\Property(property="basket_id", type="integer", description="id корзины"),
 *     @OA\Property(property="store_id", type="integer", description="id склада"),
 *     @OA\Property(property="offer_id", type="integer", description="id предложения мерчанта"),
 *     @OA\Property(property="name", type="string", description="название товара"),
 *     @OA\Property(property="qty", type="integer", description="кол-во"),
 *     @OA\Property(property="price", type="number", description="цена"),
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
    
    protected static function boot()
    {
        parent::boot();
        
        self::created(function (self $cartItem) {
            if ($cartItem->basket->order_id) {
                OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $cartItem->basket->order_id, $cartItem);
            }
        });
    
        self::updated(function (self $cartItem) {
            if ($cartItem->basket->order_id) {
                OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $cartItem->basket->order_id, $cartItem);
            }
        });
    
        self::deleting(function (self $cartItem) {
            if ($cartItem->basket->order_id) {
                OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $cartItem->basket->order_id, $cartItem);
            }
        });
    }
}
