<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReceiptTypesInPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('is_receipt_sent', 'is_prepayment_receipt_sent');
            $table->boolean('is_fullpayment_receipt_sent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('is_prepayment_receipt_sent', 'is_receipt_sent');
            $table->dropColumn('is_fullpayment_receipt_sent');
        });
    }
}
