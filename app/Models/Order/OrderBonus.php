<?php

namespace App\Models\Order;

use Carbon\Carbon;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Промокоды применённые к заказу",
 *     @OA\Property(property="order_id", type="integer", description="ID заказа"),
 *     @OA\Property(property="bonus_id", type="integer", description="ID бонуса"),
 *     @OA\Property(property="customer_bonus_id", type="integer", description="идентификатор бонуса клиента"),
 *     @OA\Property(property="name", type="string", description="название"),
 *     @OA\Property(property="type", type="integer", description="тип"),
 *     @OA\Property(property="status", type="integer", description="id статуса"),
 *     @OA\Property(property="bonus", type="integer", description="id бонуса"),
 *     @OA\Property(property="valid_period", type="integer", description="действительный период"),
 *     @OA\Property(property="items", type="integer", description=""),
 * )
 *
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
class OrderBonus extends AbstractModel
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
    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $casts = ['items' => 'array'];

    /**
     * Подтверждение удержанных бонусов
     */
    public function approveBonus(): bool
    {
        try {
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $customerId = $this->order->customer_id;
            $customerBonusId = $this->customer_bonus_id;
            $expirationDate = $this->getExpirationDate();
            if (!empty($expirationDate)) {
                $expirationDate = $expirationDate->toDateString();
            }

            $customerService->approveBonus($customerId, $customerBonusId, $expirationDate);

            $this->status = self::STATUS_ACTIVE;
            $this->save();
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCEL,
        ]);
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
