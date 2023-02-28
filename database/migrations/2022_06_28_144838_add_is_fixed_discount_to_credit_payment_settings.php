<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddIsFixedDiscountToCreditPaymentSettings extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up()
    {
        DB::table(self::TABLE_NAME)
            ->where('id', PaymentMethod::CREDITLINE_PAID)
            ->update(['settings->is_fixed_discount' => false]);
    }

    public function down()
    {
        //
    }
}
