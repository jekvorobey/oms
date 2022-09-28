<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Документы к заказам",
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         description="ID заказа"
 *     ),
 *     @OA\Property(
 *         property="file_id",
 *         type="integer",
 *         description="ID файла"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Тип документа"
 *     ),
 * )
 *
 * Class OrderDocument
 * @package App\Models\Order
 *
 * @property int $order_id
 * @property int $file_id
 * @property string $type
 *
 * @property Order $order
 */
class OrderDocument extends AbstractModel
{
    public const INVOICE_OFFER_TYPE = 'invoice_offer';
    public const UPD_TYPE = 'upd';
    public const RECEIPT_INVOICE = 'receipt_invoice';

    /** @var string */
    protected $table = 'orders_documents';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'order_id',
        'file_id',
        'type',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;
    /** @var bool */
    protected static $unguarded = true;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
