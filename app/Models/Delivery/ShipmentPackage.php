<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     description="Коробка отправления",
 *     @OA\Property(property="shipment_id", type="integer", description="id посылки"),
 *     @OA\Property(property="package_id", type="integer", description="id корзины"),
 *     @OA\Property(property="xml_id", type="number", description="количество"),
 *     @OA\Property(property="width", type="integer", description="ширина"),
 *     @OA\Property(property="height", type="integer", description="высота"),
 *     @OA\Property(property="length", type="integer", description="длина"),
 *     @OA\Property(property="weight", type="integer", description="вес (расчитывается автоматически)"),
 *     @OA\Property(property="wrapper_weight", type="integer", description="вес обертки"),
 *     @OA\Property(property="shipment", type="array", @OA\Items(ref="#/components/schemas/Shipment")),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackageItem")),
 * )
 * Коробка отправления
 * Class ShipmentPackage
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $package_id
 *
 * @property string $xml_id - идентификатор места в заказе на доставку в службе доставки
 * @property float $width
 * @property float $height
 * @property float $length
 * @property float $weight - вес (расчитывается автоматически)
 * @property float $wrapper_weight
 *
 * @property-read Shipment $shipment
 * @property-read Collection|ShipmentPackageItem[] $items
 */
class ShipmentPackage extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'shipment_id',
        'package_id',
        'status',
        'width',
        'height',
        'length',
        'wrapper_weight',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'shipment_packages';

    /** @var array */
    protected $casts = [
        'wrapper_weight' => 'float',
        'weight' => 'float',
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
    ];

    /** @var array */
    protected static $restIncludes = ['shipment'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentPackageItem::class);
    }

    public function recalcWeight(bool $save = true): void
    {
        $this->weight = $this->wrapper_weight + $this->items->reduce(function ($sum, ShipmentPackageItem $item) {
            return $sum + $item->basketItem->product['weight'] * $item->qty;
        });

        if ($save) {
            $this->save();
        }
    }
}
