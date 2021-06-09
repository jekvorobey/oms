<?php

namespace App\Models\Order;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     description="Класс-модель для сущности 'Информация о заказах во внешних системах'",
 *     @OA\Property(property="order_id", type="integer", description="ID заказа"),
 *     @OA\Property(property="merchant_integration_id", type="integer", description="ID интеграции мерчанта со внешней системой"),
 *     @OA\Property(property="order_xml_id", type="string", description="ID заказа во внешней системе"),
 *     @OA\Property(property="order", type="array", @OA\Items(ref="#/components/schemas/Order")),
 * )
 * Класс-модель для сущности "Информация о заказах во внешних системах"
 * Class OrderExport
 * @package App\Models\Order
 *
 * @property int $order_id - id заказа
 * @property int $merchant_integration_id - id интеграции мерчанта со внешней системой
 * @property string $order_xml_id - id заказа во внешней системе
 *
 * @property Order $order - заказ
 */
class OrderExport extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['order_id', 'merchant_integration_id', 'order_xml_id'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'orders_export';

    /** @var array */
    protected static $restIncludes = ['order'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
