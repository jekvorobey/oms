<?php

namespace App\Models\Order;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\OmsModel;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property int $basket_id - id корзины
 *
 * @property string $number - номер
 * @property float $cost - стоимость
 * @property int $status - статус
 * @property int $payment_status - статус оплаты
 * @property string $manager_comment - комментарий
 *
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property int $delivery_method - способ доставки
 * @property array $delivery_address
 * @property string $delivery_comment
 * @property string $receiver_name
 * @property string $receiver_phone
 * @property string $receiver_email
 *
 * @property Basket $basket - корзина
 * @property Collection|BasketItem[] $basketItems - элементы в корзине для заказа
 * @property Collection|Payment[] $payments - оплаты заказа
 * @property Collection|Delivery[] $shipments
 */
class Order extends OmsModel
{
    protected $casts = [
        'delivery_address' => 'array'
    ];
    protected static $unguarded = true;
    /**
     * @return HasOne
     */
    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class, 'id', 'basket_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
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
            if ($order->basket) {
                $order->basket->delete();
            }
            foreach ($order->shipments as $package) {
                $package->delete();
            }
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $order->id, $order);
        });
    }
}
