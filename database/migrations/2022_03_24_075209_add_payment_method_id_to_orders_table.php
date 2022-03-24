<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Payment\PaymentMethod;

class AddPaymentMethodIdToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $paymentMethod = PaymentMethod::query()
                ->where('is_postpaid', false)
                ->where('active', true)
                ->first();
            $table->integer('payment_method_id', false, true)->after('payment_status')->default($paymentMethod->id);
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_method_id');
        });
    }
}
