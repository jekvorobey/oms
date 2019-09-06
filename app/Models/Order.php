<?php

namespace App\Models;

use App\Models\Payment\Payment;
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
 */
class Order extends Model
{
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
}
