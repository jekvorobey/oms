<?php

namespace App\Models;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $id
 * @property int $customer_id - id покупателя
 * @property string $number - номер
 * @property float $cost - стоимость
 * @property int $status - статус
 * @property int $payment_status - статус оплаты
 * @property int $reserve_status - статус резерва
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property int $delivery_method - способ доставки
 * @property Carbon $processing_time - срок обработки (укомплектовки)
 * @property Carbon $delivery_time - срок доставки
 * @property string $comment - комментарий
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Basket $basket - корзина
 * @property Collection|BasketItem[] $basketItems - элементы в корзине для заказа
 * @property Collection|Payment[] $payments - оплаты заказа
 *
 * @method static find(int|array $id): self|null
 */
class Order extends Model
{
    
    protected static $unguarded = true;
    /**
     * @return HasOne
     */
    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    
    /**
     * Создать корзину, прявязанную к заказу.
     *
     * @return Basket|null
     */
    protected function createBasket(): ?Basket
    {
        $basket = new Basket();
        $basket->customer_id = $this->customer_id;
        $basket->order_id = $this->id;
        return $basket->save() ? $basket : null;
    }
    
    /**
     * Получить существующую или создать новую корзину заказа.
     *
     * @return Basket|null
     */
    public function getOrCreateBasket(): ?Basket
    {
        $basket = $this->basket;
        if (!$basket) {
            $basket = $this->createBasket();
        }
        return $basket;
    }

    /**
     * Обновить статус оплаты заказа в соотвествии со статусами оплат
     */
    public function refreshStatus()
    {
        $all = $this->payments->count();
        $statuses = [];
        foreach ($this->payments as $payment) {
            $statuses[$payment->status] = isset($statuses[$payment->status]) ? $statuses[$payment->status] + 1 : 1;
        }
        
        // todo уточнить логику смены статуса
        if ($this->allIs($statuses, $all, PaymentStatus::DONE)) {
            $this->payment_status = PaymentStatus::DONE;
        } elseif ($this->atLeastOne($statuses, PaymentStatus::TIMEOUT) && !$this->atLeastOne($statuses, PaymentStatus::DONE)) {
            $this->payment_status = PaymentStatus::TIMEOUT;
            $this->status = OrderStatus::CANCEL;
        } elseif ($this->payment_status == PaymentStatus::CREATED && $this->atLeastOne($statuses, PaymentStatus::STARTED)) {
            $this->payment_status = PaymentStatus::STARTED;
        } elseif ($this->atLeastOne($statuses, PaymentStatus::DONE)) {
            $this->payment_status = PaymentStatus::PARTIAL_DONE;
        }
        
        $this->save();
    }
    
    protected function allIs(array $statuses, int $count, int $status): bool
    {
        return ($statuses[$status] ?? 0) == $count;
    }
    
    protected function atLeastOne(array $statuses, int $status): bool
    {
        return ($statuses[$status] ?? 0) > 0;
    }
    
    /**
     * Отменить заказ.
     */
    public function cancel(): void
    {
        $this->status = OrderStatus::CANCEL;
        $this->save();
    }
    
    protected static function boot()
    {
        parent::boot();
        
        self::created(function (self $order) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $order->id, $order);
        });
        
        self::updated(function (self $order) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $order->id, $order);
        });
    
        self::deleting(function (self $order) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $order->id, $order);
        });
    }
}
