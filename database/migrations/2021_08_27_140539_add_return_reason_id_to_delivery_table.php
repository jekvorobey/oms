<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReturnReasonIdToDeliveryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->bigInteger('return_reason_id')->unsigned()->nullable()->after('is_canceled');
            $table->foreign('return_reason_id')->references('id')->on('order_return_reasons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->dropForeign('delivery_return_reason_id_foreign');
            $table->dropColumn('return_reason_id');
        });
    }
}
