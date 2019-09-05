<?php

namespace App\Models\Payment;

use Illuminate\Support\Fluent;

/**
 * Class PaymentPart
 * @package App\Models\Payment
 *
 * @property string $id
 * @property int $type
 * @property float $sum
 * @property int $status
 * @property string $payed_at
 */
class PaymentPart extends Fluent
{

}
