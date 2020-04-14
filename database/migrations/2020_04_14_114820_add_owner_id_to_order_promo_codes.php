<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOwnerIdToOrderPromoCodes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_promo_codes', function (Blueprint $table) {
            $table->dropColumn('is_personal');
            $table->bigInteger('owner_id')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_promo_codes', function (Blueprint $table) {
            $table->boolean('is_personal');
            $table->dropColumn('owner_id');
        });
    }
}
