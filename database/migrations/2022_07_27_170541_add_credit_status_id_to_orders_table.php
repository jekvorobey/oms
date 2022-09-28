<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditStatusIdToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('credit_status_id')->nullable();
            $table->decimal('credit_discount', 18, 4)->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_credit_receipt_sent')->default(false);
            $table->boolean('is_credit_payment_receipt_sent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('credit_status_id');
            $table->dropColumn('credit_discount');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_credit_receipt_sent')->default(false);
            $table->boolean('is_credit_payment_receipt_sent')->default(false);
        });
    }
}
