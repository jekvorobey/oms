<?php

namespace App\Models\Order;

use App\Core\Notifications\OrderNotification;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\OmsModel;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Builder;
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
 * @property OrderComment $comment - коментарий покупателя к заказу
 * @property Collection|OrderDiscount[] $discounts - скидки к заказу
 * @property Collection|OrderPromoCode[] $promoCodes - промокоды применённые к заказу
 * @property Collection|OrderBonus[] $bonuses - бонусы применённые к заказу
 */
class Order extends OmsModel
{
    /** @var string */
    public $notificator = OrderNotification::class;

    /** @var UserDto */
    protected $user;
    /** @var CustomerDto */
    protected $customer;

    /** @var array */
    protected $casts = [
        'certificates' => 'array',
    ];

    /**
     * @return integer
     */
    public static function makeNumber(): int
    {
        $ordersCount = (self::all()->last()->id ?? 0) + 1000000;
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
     * @return HasMany
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class, 'order_id');
    }

    /**
     * @return HasMany
     */
    public function promoCodes(): HasMany
    {
        return $this->hasMany(OrderPromoCode::class, 'order_id');
    }

    /**
     * @return HasMany
     */
    public function bonuses(): HasMany
    {
        return $this->hasMany(OrderBonus::class, 'order_id');
    }

    /**
     * Учитывать те заказы, в которых использовалась скидка $discountId,
     * данная скидка должна быть либо активирована без промокода,
     * либо активирована промокодом, но со статусом ACTIVE
     *
     * @param Builder $query
     * @param int     $discountId
     */
    public function scopeForDiscountReport(Builder $query, int $discountId)
    {
        $query
            ->whereHas('discounts', function (Builder $query) use ($discountId) {
                $query->where('discount_id', $discountId);
            })
            ->whereDoesntHave('promoCodes', function (Builder $query) use ($discountId) {
                $query
                    ->where('discount_id', $discountId)
                    ->where('status', '!=', OrderPromoCode::STATUS_ACTIVE);
            });
    }

    public function getUser(): UserDto
    {
        if (is_null($this->customer)) {
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $this->customer = $customerService->customers((new RestQuery())->setFilter('id', $this->customer_id))->first();
        }
        if (is_null($this->user)) {
            /** @var UserService $userService */
            $userService = resolve(UserService::class);
            $this->user = $userService->users((new RestQuery())->setFilter('id', $this->customer->user_id))->first();
        }

        return $this->user;
    }

    /**
     * @todo брать почту пользователя оформившего заказ
     * @return string
     */
    public function customerEmail(): string
    {
        return 'mail@example.com';
    }

    /**
     * Заказ оплачен?
     * @return bool
     */
    public function isPaid(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::PAID, PaymentStatus::HOLD]);
    }

    /**
     * Заказ может быть обработан?
     * @return bool
     */
    public function canBeProcessed(): bool
    {
        /*
         * todo В будущем, когда будут заказы с постоплатой, добавить сюда доп проверку,
         * что заказ с постоплатой и может быть обработан без оплаты
         */
        return $this->isPaid();
    }
}
