<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Cargo
 * @package App\Models\Delivery
 *
 * @property int $status
 *
 * @property int $width
 * @property int $height
 * @property int $length
 * @property int $weight
 *
 * @property-read Collection|Shipment[] $shipments
 */
class Cargo extends OmsModel
{
    private const SIDES = ['width', 'height', 'length'];
    
    protected $table = 'cargo';
    
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
    
    public function recalc()
    {
        $weight = 0;
        $volume = 0;
        $maxSide = 0;
        $maxSideName = 'width';
        foreach ($this->shipments as $shipment) {
            foreach ($shipment->packages as $package) {
                $weight += $package->weight;
                $volume += $package->width * $package->height * $package->length;
                foreach (self::SIDES as $side) {
                    if ($package[$side] > $maxSide) {
                        $maxSide = $package[$side];
                        $maxSideName = $side;
                    }
                }
            }
        }
        $this->weight = $weight;
        $avgSide = pow($volume, 1/3);
        if ($maxSide <= $avgSide) {
            foreach (self::SIDES as $side) {
                $this[$side] = $avgSide;
            }
        } else {
            $otherSide = sqrt($volume/$maxSide);
            foreach (self::SIDES as $side) {
                if ($side == $maxSideName) {
                    $this[$side] = $maxSide;
                } else {
                    $this[$side] = $otherSide;
                }
            }
        }
        
        $this->save();
    }
}
