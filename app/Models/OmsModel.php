<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class OmsModel
 * @package App\Models
 *
 * @property int $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static static find(int|array $id)
 */
class OmsModel extends Model
{
    /** @var bool */
    protected static $unguarded = true;
}

