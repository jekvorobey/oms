<?php

namespace App\Models\Order;

use App\Models\History\History;
use App\Models\WithHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Greensight\CommonMsa\Models\AbstractModel;

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
 * @property string $refund_id - ID возврата из платежной системы
 *
 * @property string $number - номер возврата по заказу (для формирования использовать метод \App\Models\Order\OrderReturn::makeNumber())
 * @property float $price - сумма к возврату
 * @property bool $is_delivery - флаг доставки
 * @property float $commission - сумма удержанной комиссии
 * @property int $status - статус (см. \App\Models\Order\OrderReturnStatus)
 * @property Carbon|null $status_at - дата установки статуса
 *
 * @property-read Collection|OrderReturnItem[] $items - состав возврата
 * @property-read Order $order - заказ
 * @property Collection|History[] $history - история изменений
 */
class OrderReturn extends AbstractModel
{
    use WithHistory;

    public const STATUS_CREATED = 1;
    public const STATUS_DONE = 2;
    public const STATUS_FAILED = 3;

    /** @var bool */
    protected static $unguarded = true;

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function historyMainModel(): ?Order
    {
        return $this->order;
    }

    /**
     * Сформировать номер для возврата
     */
    public static function makeNumber(int $orderId): string
    {
        /** @var Order $order */
        $order = Order::query()->firstOrFail($orderId)->load('orderReturns');

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
