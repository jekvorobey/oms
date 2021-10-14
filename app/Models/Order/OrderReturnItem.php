<?php

namespace App\Models\Order;

use App\Models\Basket\BasketItem;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     description="Состав возврата по заказу",
 *     @OA\Property(property="order_return_id", type="integer", description="ID возврата по заказу"),
 *     @OA\Property(property="basket_item_id", type="integer", description="ID возвращаемого элемента корзины"),
 *     @OA\Property(property="offer_id", type="integer", description="ID предложения к возврату"),
 *     @OA\Property(property="referrer_id", type="integer", description="ID РП, по чьей ссылке товар был добавлен в корзину"),
 *     @OA\Property(property="bundle_id", type="integer", description="ID бандла, в который входит этот товар"),
 *     @OA\Property(property="type", type="integer", description="тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)"),
 *     @OA\Property(property="product", type="string", description="данные зависящие от типа товара"),
 *     @OA\Property(property="name", type="string", description="название товара к возврату"),
 *     @OA\Property(property="qty", type="number", description="кол-во товара к возврату"),
 *     @OA\Property(property="price", type="number", description="сумма к возврату ( * qty)"),
 *     @OA\Property(property="commission", type="number", description="сумма удержанной комиссии ( * qty)"),
 *     @OA\Property(property="orderReturn", type="array", @OA\Items(ref="#/components/schemas/OrderReturn")),
 *     @OA\Property(property="basketItem", type="array", @OA\Items(ref="#/components/schemas/BasketItem")),
 * )
 *
 * Класс-модель для сущности "Состав возврата по заказу"
 * Class OrderReturnItem
 * @package App\Models\Order
 * @property int $order_return_id - id возврата по заказу
 * @property int $basket_item_id - id возвращаемого элемента корзины
 * @property int $offer_id - id предложения к возврату
 * @property int|null $referrer_id - ID РП, по чьей ссылке товар был добавлен в корзину
 * @property int|null $bundle_id - id бандла, в который входит этот товар
 * @property int $type - тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 * @property array $product - данные зависящие от типа товара
 * @property string $name - название товара к возврату
 * @property float $qty - кол-во товара к возврату
 * @property float|null $price - сумма к возврату ( * qty)
 * @property float $commission - сумма удержанной комиссии ( * qty)
 *
 * @property-read OrderReturn $orderReturn - возврат по заказу
 * @property-read BasketItem $basketItem - элемент корзины
 */
class OrderReturnItem extends Model
{
    /** @var bool */
    protected static $unguarded = true;

    protected $casts = [
        'product' => 'json',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function basketItem(): BelongsTo
    {
        return $this->belongsTo(BasketItem::class);
    }

    public static function boot()
    {
        parent::boot();

        self::created(function (OrderReturnItem $item) {
            $order = $item->orderReturn->order;
            $basketItem = $item->basketItem;

            app(ServiceNotificationService::class)->send($order->getUser()->id, 'servisnyeizmenenie_zakaza_sostav_zakaza', [
                'ORDER_ID' => $order->id,
                'CUSTOMER_NAME' => $order->getUser()->first_name,
                'LINK_ORDER' => sprintf('%s/profile/orders/%d', config('app.showcase_host'), $order->id),
                'NAME_GOODS' => $basketItem->name,
                'PART_PRICE' => (int) $basketItem->cost,
                'NUMBER' => (int) $item->qty,
                'DELIVERY_PRICE' => (int) $basketItem->shipmentItem->shipment->cost,
                'TOTAL_PRICE' => (int) $order->cost,
                'REFUND_ORDER' => (int) $item->price,
            ]);
        });
    }
}
