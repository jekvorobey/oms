<?php

namespace App\Models\Order;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
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
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property int $delivery_service - служба доставки (DPD, CDEK и т.д.)
 * @property int $delivery_method - способ доставки (самовывоз из ПВЗ, самовывоз из постомата, доставка)
 *
 * @property string $number - номер
 * @property float $cost - стоимость
 * @property int $status - статус
 * @property int $payment_status - статус оплаты
 * @property string $manager_comment - комментарий менеджера
 * @property float $delivery_cost - стоимость доставки iBT
 * @property array $delivery_address - адрес доставки
 * @property string $delivery_comment - комментарий к доставке
 * @property string $receiver_name - имя получателя
 * @property string $receiver_phone - телефон получателя
 * @property string $receiver_email - e-mail получателя
 *
 * @property Basket $basket - корзина
 * @property Collection|BasketItem[] $basketItems - элементы в корзине для заказа
 * @property Collection|Payment[] $payments - оплаты заказа
 * @property Collection|Delivery[] $deliveries - доставка заказа
 *
 * @OA\Schema(
 *     schema="OrderItem",
 *     @OA\Property(property="id", type="integer", description="id заказа"),
 *     @OA\Property(property="customer_id", type="integer", description="id покупателя"),
 *     @OA\Property(property="basket_id", type="integer", description="id корзины"),
 *     @OA\Property(property="basket", ref="#/components/schemas/BasketItem"),
 *     @OA\Property(property="delivery_type", type="string", description="тип доставки (одним отправлением, несколькими отправлениями)"),
 *     @OA\Property(property="delivery_service", type="string", description="служба доставки (DPD, CDEK и т.д.)"),
 *     @OA\Property(property="delivery_method", type="string", description="способ доставки (самовывоз из ПВЗ, самовывоз из постомата, доставка)"),
 *     @OA\Property(property="number", type="string", description="номер"),
 *     @OA\Property(property="cost", type="number", description="стоимость"),
 *     @OA\Property(property="payment_status", type="integer", description="статус оплаты"),
 *     @OA\Property(property="manager_comment", type="string", description="комментарий менеджера"),
 *     @OA\Property(property="delivery_address", type="string", description="адрес доставки"),
 *     @OA\Property(property="delivery_comment", type="string", description="комментарий к доставке"),
 *     @OA\Property(property="receiver_name", type="string", description="имя получателя"),
 *     @OA\Property(property="receiver_phone", type="string", description="телефон получателя"),
 *     @OA\Property(property="receiver_email", type="string", description="e-mail получателя"),
 * )
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function comment(): HasOne
    {
        return $this->HasOne(OrderComment::class);
    }
    
    /**
     * Пересчитать сумму товаров заказа
     */
    public function costRecalc(bool $save = true): void
    {
        $cost = 0.0;
        $this->load('basket.items', 'deliveries');
        
        //Считаем сумму позиций в корзине
        foreach ($this->basket->items as $item) {
            $cost += $item->cost;
        }
        //Прибавляем стоимость за доставку
        $cost += $this->delivery_cost;
        
        $this->cost = $cost;
        if ($save) {
            $this->save();
        }
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
        if ($this->allIs($statuses, $all, PaymentStatus::STATUS_DONE)) {
            $this->payment_status = PaymentStatus::STATUS_DONE;
        } elseif ($this->atLeastOne($statuses, PaymentStatus::STATUS_TIMEOUT) && !$this->atLeastOne($statuses, PaymentStatus::STATUS_DONE)) {
            $this->payment_status = PaymentStatus::STATUS_TIMEOUT;
            $this->status = OrderStatus::STATUS_CANCEL;
        } elseif ($this->payment_status == PaymentStatus::STATUS_CREATED && $this->atLeastOne($statuses, PaymentStatus::STATUS_STARTED)) {
            $this->payment_status = PaymentStatus::STATUS_STARTED;
        } elseif ($this->atLeastOne($statuses, PaymentStatus::STATUS_DONE)) {
            $this->payment_status = PaymentStatus::STATUS_PARTIAL_DONE;
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
        $this->status = OrderStatus::STATUS_CANCEL;
        $this->save();
    }

    protected static function boot()
    {
        parent::boot();
    
        self::saving(function (self $order) {
            if ($order->delivery_cost != $order->getOriginal('delivery_cost')) {
                $order->costRecalc(false);
            }
        });

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
            foreach ($order->deliveries as $delivery) {
                $delivery->delete();
            }
            
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $order->id, $order);
        });
    }
}
