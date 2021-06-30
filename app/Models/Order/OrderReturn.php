<?php

namespace App\Models\Order;

use App\Models\History\History;
use App\Models\History\HistoryMainEntity;
use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     description="Возврат по заказу",
 *     @OA\Property(property="order_id", type="integer", description="ID заказа"),
 *     @OA\Property(property="customer_id", type="integer", description="ID бонуса"),
 *     @OA\Property(property="number", type="integer", description="идентификатор бонуса клиента"),
 *     @OA\Property(property="price", type="string", description="название"),
 *     @OA\Property(property="commission", type="integer", description="тип"),
 *     @OA\Property(property="status", type="integer", description="id статуса"),
 *     @OA\Property(property="status_at", type="integer", description="id бонуса"),
 *     @OA\Property(property="basket", type="array", @OA\Items(ref="#/components/schemas/Basket")),
 * )
 *
 * Класс-модель для сущности "Возврат по заказу"
 * Class OrderReturn
 * @package App\Models\Order
 *
 * @property int $order_id - id заказа
 * @property int $customer_id - id покупателя
 * @property int $type - тип возвращаемого заказа (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 *
 * @property string $number - номер возврата по заказу (для формирования использовать метод \App\Models\Order\OrderReturn::makeNumber())
 * @property float $price - сумма к возврату
 * @property float $commission - сумма удержанной комиссии
 * @property int $status - статус (см. \App\Models\Order\OrderReturnStatus)
 * @property Carbon|null $status_at - дата установки статуса
 *
 * @property-read Collection|OrderReturnItem[] $items - состав возврата
 * @property-read Order $order - заказ
 * @property Collection|History[] $history - история изменений
 */
class OrderReturn extends OmsModel
{
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function history(): MorphToMany
    {
        return $this->morphToMany(History::class, 'main_entity', (new HistoryMainEntity())->getTable());
    }

    /**
     * Сформировать номер для возврата
     */
    public static function makeNumber(int $orderId): string
    {
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('orderReturns')->get();

        return $order->number . '-return-' . ($order->orderReturns->count() + 1);
    }

    /**
     * Пересчитать сумму к возрату
     */
    public function priceRecalc(bool $save = true): void
    {
        $price = 0.0;
        $this->loadMissing('items');

        foreach ($this->items as $item) {
            $price += $item->price;
        }

        $this->price = $price;

        if ($save) {
            $this->save();
        }
    }

    /**
     * Пересчитать сумму удержанной комиссии
     */
    public function commissionRecalc(bool $save = true): void
    {
        $commission = 0.0;
        $this->loadMissing('items');

        foreach ($this->items as $item) {
            $commission += $item->commission;
        }

        $this->commission = $commission;

        if ($save) {
            $this->save();
        }
    }
}
