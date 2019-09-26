<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ShipmentPackage
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $cargo_id
 * @property int $status
 * @property array $items
 *
 * @property int $width
 * @property int $height
 * @property int $length
 * @property int $weight
 * @property int $wrapper_weight
 *
 * @property-read Shipment $shipment
 */
class ShipmentPackage extends OmsModel
{
    protected $casts = [
        'items' => 'array'
    ];
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
    
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
    
    public function recalcWeight()
    {
        $this->weight = $this->wrapper_weight + array_reduce((array)$this->items, function ($sum, $product) {
            return $sum + $product['weight'] * $product['qty'];
        });
    }
    
    public function setWrapper(int $weight, int $width, int $height, int $length)
    {
        $this->wrapper_weight = $weight;
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->recalcWeight();
    }
    
    public function setProduct(int $offerId, array $data)
    {
        $edited = false;
        $products = (array)$this->items;
        $toDelete = null;
        foreach ($products as $i => &$product) {
            if ($product['offer_id'] == $offerId) {
                $edited = true;
                if (isset($data['qty']) && $data['qty'] === 0) {
                    $toDelete = $i;
                    break;
                }
                foreach ($data as $field => $value) {
                    $product[$field] = $value;
                }
            }
        }
        if ($toDelete !== null) {
            unset($products[$toDelete]);
        }
        if (!$edited) {
            $data['offer_id'] = $offerId;
            $products[] = $data;
        }
        $this->items = $products;
        $this->recalcWeight();
    }
}
