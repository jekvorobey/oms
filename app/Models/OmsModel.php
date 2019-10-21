<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
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
class OmsModel extends AbstractModel
{
    /** @var bool */
    protected static $unguarded = true;
}

