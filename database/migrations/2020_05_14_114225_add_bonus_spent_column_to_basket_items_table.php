<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBonusSpentColumnToBasketItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('basket_items', function (Blueprint $table) {
            $table->integer('bonus_spent')
                ->unsigned()
                ->default(0)
                ->after('cost');

            $table->integer('bonus_discount')
                ->unsigned()
                ->default(0)
                ->after('bonus_spent');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('basket_items', function (Blueprint $table) {
            $table->dropColumn('bonus_spent');
            $table->dropColumn('bonus_discount');
        });
    }
}
