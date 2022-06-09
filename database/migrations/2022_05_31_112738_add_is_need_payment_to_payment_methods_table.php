<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsNeedPaymentToPaymentMethodsTable extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up()
    {
        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->boolean('is_need_payment')->after('is_postpaid')->default(false);
        });

        DB::table(self::TABLE_NAME)->where('code', PaymentMethod::PREPAID)->update(['is_need_payment' => true]);
    }

    public function down()
    {
        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->dropColumn('is_need_payment');
        });
    }
}
