<?php

namespace App\Models\Order;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
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
class OrderReturnItem extends OmsModel
{
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
