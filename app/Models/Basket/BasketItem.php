<?php

namespace App\Models\Basket;

use App\Models\OmsModel;
use App\Models\Order\OrderHistoryEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Класс-модель для сущности "Элемент корзины"
 * Class BasketItem
 * @package App\Models
 *
 * @property int $basket_id - id корзины
 * @property int $offer_id - id предложения мерчанта
 * @property string $name - название товара
 * @property float $qty - кол-во
 * @property float|null $price - цена за единицу товара
 * @property bool $is_reserved - товар зарезервирован?
 * @property int $reserved_by - кем зарезервирован
 * @property Carbon $reserved_at - когда зарезервирован
 *
 * @property-read Basket $basket
 *
 * @OA\Schema(
 *     schema="BasketItem",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="basket_id", type="integer"),
 *     @OA\Property(property="offer_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="qty", type="integer"),
 *     @OA\Property(property="is_reserved", type="integer"),
 *     @OA\Property(property="reserved_by", type="integer"),
 *     @OA\Property(property="reserved_at", type="string"),
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
