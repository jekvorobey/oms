<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Carbon\Carbon;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Информация о бонусах, полученных в заказе
 * Class OrderBonus
 * @package App\Models\Order
 *
 * @property int $order_id
 * @property int $bonus_id
 * @property int $customer_bonus_id
 * @property string $name
 * @property int $type
 * @property int $status
 * @property int $bonus
 * @property int $valid_period (период действия бонуса в днях)
 * @property array|null $items
 */
class OrderBonus extends OmsModel
{
    public const STATUS_ON_HOLD = 1; // На удержании
    public const STATUS_ACTIVE = 2; // Активные
    public const STATUS_CANCEL = 3; // Отменены

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'order_id',
        'bonus_id',
        'customer_bonus_id',
        'name',
        'type',
        'status',
        'bonus',
        'valid_period',
        'items',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var array */
    protected $casts = ['items' => 'array'];

    /**
     * Подтверждение удержанных бонусов
     * @return bool
     */
    public function approveBonus()
    {
        try {
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $customerId = $this->order->customer_id;
            $customerBonusId = $this->customer_bonus_id;
            $expirationDate = $this->getExpirationDate()->toDateString();
            $customerService->approveBonus($customerId, $customerBonusId, $expirationDate);

            $this->status = self::STATUS_ACTIVE;
            $this->save();
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @return Carbon|null
     */
    public function getExpirationDate()
    {
        return $this->valid_period ? Carbon::now()->addDays($this->valid_period + 1) : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
