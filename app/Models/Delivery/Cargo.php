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
 * @property-read Collection|ShipmentPackage[] $packages
 */
class Cargo extends OmsModel
{
    protected $table = 'cargo';
    
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }
}
