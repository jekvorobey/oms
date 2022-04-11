<?php

namespace App\Models\Order;

use App\Core\Notifications\NotificationInterface;
use App\Core\Notifications\OrderNotification;
use App\Models\Basket\Basket;
use App\Models\Delivery\Delivery;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\WithMainHistory;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Заказы",
 *     @OA\Property(
 *         property="customer_id",
 *         type="integer",
 *         description="id покупателя"
 *     ),
 *     @OA\Property(
 *         property="basket_id",
 *         type="integer",
 *         description="id корзины"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="integer",
 *         description="тип корзины (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)"
 *     ),
 *     @OA\Property(
 *         property="receiver_name",
 *         type="string",
 *         description="имя получателя (используется только при покупке мастер-классов)"
 *     ),
 *     @OA\Property(
 *         property="receiver_phone",
 *         type="string",
 *         description="телефон получателя (используется только при покупке мастер-классов)"
 *     ),
 *     @OA\Property(
 *         property="receiver_email",
 *         type="string",
 *         description="e-mail получателя (используется только при покупке мастер-классов)"
 *     ),
 *     @OA\Property(
 *         property="cost",
 *         type="number",
 *         description="стоимость (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="price",
 *         type="number",
 *         description="стоимость (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="spent_bonus",
 *         type="integer",
 *         description=""
 *     ),
 *     @OA\Property(
 *         property="added_bonus",
 *         type="integer",
 *         description=""
 *     ),
 *     @OA\Property(
 *         property="certificates",
 *         type="string",
 *         description="Массив сертификатов"
 *     ),
 *     @OA\Property(
 *         property="delivery_type",
 *         type="integer",
 *         description="тип доставки (см. \App\Models\Delivery\DeliveryType)"
 *     ),
 *     @OA\Property(
 *         property="delivery_price",
 *         type="integer",
 *         description="стоимость доставки iBT (с учетом скидки)"
 *     ),
 *     @OA\Property(
 *         property="delivery_cost",
 *         type="number",
 *         description="стоимость доставки iBT (без учета скидки)"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="стоимость доставки iBT (без учета скидки)"
 *     ),
 *     @OA\Property(
 *         property="status_at",
 *         type="string",
 *         description="дата установки статуса заказа"
 *     ),
 *     @OA\Property(
 *         property="payment_status",
 *         type="string",
 *         description="статус оплаты (см. \App\Models\Payment\PaymentStatus)"
 *     ),
 *     @OA\Property(
 *         property="payment_status_at",
 *         type="string",
 *         description="дата установки статуса оплаты"
 *     ),
 *     @OA\Property(
 *         property="is_problem",
 *         type="integer",
 *         description="флаг, что заказ проблемный"
 *     ),
 *     @OA\Property(
 *         property="is_problem_at",
 *         type="string",
 *         description="дата установки флага проблемного заказа"
 *     ),
 *     @OA\Property(
 *         property="is_canceled",
 *         type="integer",
 *         description="флаг, что заказ отменен"
 *     ),
 *     @OA\Property(
 *         property="is_postpaid",
 *         type="integer",
 *         description="флаг, что заказ с постоплатой"
 *     ),
 *     @OA\Property(
 *         property="is_canceled_at",
 *         type="string",
 *         description="дата установки флага отмены заказа"
 *     ),
 *     @OA\Property(
 *         property="is_require_check",
 *         type="integer",
 *         description="флаг, что заказ требует проверки"
 *     ),
 *     @OA\Property(
 *         property="manager_comment",
 *         type="string",
 *         description="комментарий менеджера"
 *     ),
 *     @OA\Property(
 *         property="confirmation_type",
 *         type="integer",
 *         description="тип подтверждения заказа (см. \App\Models\Order\OrderConfirmationType)"
 *     ),
 *     @OA\Property(
 *         property="number",
 *         type="string",
 *         description="номер"
 *     ),
 *     @OA\Property(property="basket", type="array", @OA\Items(ref="#/components/schemas/Basket")),
 *     @OA\Property(property="payments", type="array", @OA\Items(ref="#/components/schemas/Payment")),
 *     @OA\Property(property="deliveries", type="array", @OA\Items(ref="#/components/schemas/Delivery")),
 *     @OA\Property(property="comment", type="array", @OA\Items(ref="#/components/schemas/OrderComment")),
 *     @OA\Property(property="discounts", type="array", @OA\Items(ref="#/components/schemas/OrderDiscount")),
 *     @OA\Property(property="promoCodes", type="array", @OA\Items(ref="#/components/schemas/OrderPromoCode")),
 *     @OA\Property(property="bonuses", type="array", @OA\Items(ref="#/components/schemas/OrderBonus")),
 *     @OA\Property(property="orderReturns", type="array", @OA\Items(ref="#/components/schemas/OrderReturn")),
 *     @OA\Property(property="history", type="array", @OA\Items(ref="#/components/schemas/History")),
 * )
 *
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property int $basket_id - id корзины
 * @property int $type - тип заказа (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 *
 * @property string $receiver_name - имя получателя (используется только при покупке мастер-классов)
 * @property string $receiver_phone - телефон получателя (используется только при покупке мастер-классов)
 * @property string $receiver_email - e-mail получателя (используется только при покупке мастер-классов)
 *
 * @property float $cost - стоимость (расчитывается автоматически)
 * @property float $price
 * @property int $spent_bonus
 * @property int $added_bonus
 * @property array $certificates - json-массив примененных Подарочных сертификатов к заказу
 * @property float $spent_certificate - Потрачено с помощью Подарочного сертификата
 * @property float $done_return_sum - Сумма, которая уже была возвращена
 *
 * @property int $delivery_type - тип доставки (см. \App\Models\Delivery\DeliveryType)
 * @property float $delivery_price - стоимость доставки iBT (с учетом скидки)
 * @property float $delivery_cost - стоимость доставки iBT (без учета скидки)
 *
 * @property int $status - статус (см. \App\Models\Order\OrderStatus)
 * @property Carbon|null $status_at - дата установки статуса заказа
 * @property int $payment_status - статус оплаты (см. \App\Models\Payment\PaymentStatus)
 * @property int $payment_method_id - способ оплаты (см. \App\Models\Payment\PaymentMethod)
 * @property Carbon|null $payment_status_at - дата установки статуса оплаты
 * @property int $is_problem - флаг, что заказ проблемный
 * @property Carbon|null $is_problem_at - дата установки флага проблемного заказа
 * @property int $is_canceled - флаг, что заказ отменен
 * @property bool $is_partially_cancelled - флаг, что заказ отменен частично
 * @property bool $can_partially_cancelled - флаг, что заказ отменен частично
 * @property int $return_reason_id - id причины отмены доставки
 * @property Carbon|null $is_canceled_at - дата установки флага отмены заказа
 * @property int $is_require_check - флаг, что заказ требует проверки
 * @property string $manager_comment - комментарий менеджера
 * @property int $confirmation_type - тип подтверждения заказа (см. \App\Models\Order\OrderConfirmationType)
 * @property bool $is_returned - флаг возврата выполненного заказа
 * @property bool is_postpaid - флаг заказа с постоплатой
 *
 * @property string $number - номер
 *
 * //dynamic attributes
 * @property-read float $cashless_price - cумма заказа без учета подарочных сертификатов
 * @property-read float $remaining_price - cумма заказа за вычетом совершенных возвратов
 *
 * @property Basket $basket - корзина
 * @property Collection|Payment[] $payments - оплаты заказа
 * @property PaymentMethod $paymentMethod
 * @property Collection|Delivery[] $deliveries - доставка заказа
 * @property OrderComment $comment - коментарий покупателя к заказу
 * @property Collection|OrderDiscount[] $discounts - скидки к заказу
 * @property Collection|OrderPromoCode[] $promoCodes - промокоды применённые к заказу
 * @property Collection|OrderBonus[] $bonuses - бонусы применённые к заказу
 * @property Collection|OrderReturn[] $orderReturns - возвраты по заказу
 * @property OrderReturnReason $orderReturnReason - причина возврата заказа
 */
class Order extends AbstractModel
{
    use WithMainHistory;

    /** @var bool */
    protected static $unguarded = true;

    /** @var UserDto */
    protected $user;
    /** @var CustomerDto */
    protected $customer;

    /** @var array */
    protected $casts = [
        'certificates' => 'array',
    ];

    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class, 'id', 'basket_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function comment(): HasOne
    {
        return $this->hasOne(OrderComment::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class, 'order_id');
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(OrderPromoCode::class, 'order_id');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(OrderBonus::class, 'order_id');
    }

    public function orderReturns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function orderReturnReason(): BelongsTo
    {
        return $this->belongsTo(OrderReturnReason::class, 'return_reason_id');
    }

    public function historyNotificator(): ?NotificationInterface
    {
        return resolve(OrderNotification::class);
    }

    /**
     * Учитывать те заказы, в которых использовалась скидка $discountId,
     * данная скидка должна быть либо активирована без промокода,
     * либо активирована промокодом, но со статусом ACTIVE
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
            $query = (new RestQuery())
                ->setFilter('id', $this->customer_id);
            $this->customer = $customerService->customers($query)->first();
        }
        if (is_null($this->user)) {
            /** @var UserService $userService */
            $userService = resolve(UserService::class);
            $query = (new RestQuery())
                ->include('profile')
                ->setFilter('id', $this->customer->user_id);
            $this->user = $userService->users($query)->first();
        }

        return $this->user;
    }

    /**
     * Получить телефон из профиля пользователя.
     */
    public function customerPhone(): string
    {
        $user = $this->getUser();
        return $user->phone;
    }

    public function customerEmail(): ?string
    {
        $user = $this->getUser();
        return $user->email;
    }

    /**
     * Сумма заказа без учета подарочных сертификатов
     */
    public function getCashlessPriceAttribute(): float
    {
        return max(0, $this->price - $this->spent_certificate);
    }

    /**
     * Сумма заказа за вычетом совершенных возвратов
     */
    public function getRemainingPriceAttribute(): float
    {
        return max(0, $this->price - $this->done_return_sum);
    }

    /**
     * Заказ оплачен?
     */
    public function isPaid(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::PAID, PaymentStatus::HOLD]);
    }

    /**
     * Заказ может быть обработан?
     */
    public function canBeProcessed(): bool
    {
        return ($this->is_postpaid && $this->payment_status === PaymentStatus::WAITING) || $this->isPaid();
    }

    /**
     * Заказ является заказом с товарами?
     */
    public function isProductOrder(): bool
    {
        return $this->type == Basket::TYPE_PRODUCT;
    }

    /**
     * Заказ является заказом с мастер-классами?
     */
    public function isPublicEventOrder(): bool
    {
        return $this->type == Basket::TYPE_MASTER;
    }

    /**
     * Заказ является заказом порадочного сертификата?
     */
    public function isCertificateOrder(): bool
    {
        return $this->type == Basket::TYPE_CERTIFICATE;
    }

    /**
     * Заказ является консолидированным?
     */
    public function isConsolidatedDelivery(): bool
    {
        return $this->deliveries()->count() == 1;
    }

    /**
     * Заказ оплачен полностью сертификатом?
     */
    public function isFullyPaidByCertificate(): bool
    {
        return $this->spent_certificate > 0 && ($this->price - $this->spent_certificate) === 0.0;
    }
}
