<?php

namespace App\Models;

use App\Core\Notifications\NotificationInterface;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Class OmsModel
 * @package App\Models
 *
 * @method static static find(int|array $id)
 */
class OmsModel extends AbstractModel
{
    /** @var bool */
    protected static $unguarded = true;
    /** @var NotificationInterface */
    public $notificator;
}
