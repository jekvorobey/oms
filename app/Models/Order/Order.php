<?php

namespace App\Models\Order;

use App\Core\Notifications\OrderNotification;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\OmsModel;
use App\Models\Payment\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property int $basket_id - id корзины
 *
 * @property float $cost - стоимость (расчитывается автоматически)
 * @property float $price
 * @property int $spent_bonus
 * @property int $added_bonus
 * @property string $promocode
 * @property array $certificates
 *
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property float $delivery_price - стоимость доставки iBT (с учетом скидки)
 * @property float $delivery_cost - стоимость доставки iBT (без учета скидки)
 * @property string $delivery_comment - комментарий к доставке
 *
 * @property int $status - статус
 * @property Carbon|null $status_at - дата установки статуса заказа
 * @property int $payment_status - статус оплаты
 * @property Carbon|null $payment_status_at - дата установки статуса оплаты
 * @property int $is_problem - флаг, что заказ проблемный
 * @property Carbon|null $is_problem_at - дата установки флага проблемного заказа
 * @property int $is_canceled - флаг, что заказ отменен
 * @property Carbon|null $is_canceled_at - дата установки флага отмены заказа
 * @property int $is_require_check - флаг, что заказ требует проверки
 * @property string $manager_comment - комментарий менеджера
 *
 * @property string $number - номер
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
 *     @OA\Property(property="delivery_method", type="string", description="способ доставки (самовывоз из ПВЗ, самовывоз из постомата, доставка)"),
 *     @OA\Property(property="number", type="string", description="номер"),
 *     @OA\Property(property="cost", type="number", description="стоимость (расчитывается автоматически)"),
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
    /** @var string */
    public $notificator = OrderNotification::class;

    /** @var array */
    protected $casts = [
        'certificates' => 'array',
    ];

    /**
     * @return integer
     */
    public static function makeNumber(): int
    {
        $ordersCount = self::all()->last()->id+1000000;
        return (int)$ordersCount + 1;
    }

    /**
     * @return HasOne
     */
    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class, 'id', 'basket_id');
    }

    /**
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @return HasOne
     */
    public function comment(): HasOne
    {
        return $this->hasOne(OrderComment::class);
    }

    /**
     * Установить статус заказа (без сохранения!)
     * @param  int  $status
     * @return self
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        $this->status_at = now();

        return $this;
    }

    /**
     * Установить статус оплаты заказа (без сохранения!)
     * @param  int  $status
     * @return self
     */
    public function setPaymentStatus(int $status): self
    {
        $this->payment_status = $status;
        $this->payment_status_at = now();

        return $this;
    }

    /**
     * @todo брать почту пользователя оформившего заказ
     * @return string
     */
    public function customerEmail(): string
    {
        return 'mail@example.com';
    }
}
