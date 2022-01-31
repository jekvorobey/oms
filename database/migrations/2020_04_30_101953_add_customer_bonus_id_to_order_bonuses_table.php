<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomerBonusIdToOrderBonusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_bonuses', function (Blueprint $table) {
            $table->bigInteger('customer_bonus_id')->nullable()->unsigned()->after('bonus_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_bonuses', function (Blueprint $table) {
            $table->dropColumn('customer_bonus_id');
        });
    }
}
