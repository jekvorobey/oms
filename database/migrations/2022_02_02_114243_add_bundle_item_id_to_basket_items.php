<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBundleItemIdToBasketItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('basket_items', function (Blueprint $table) {
            $table->bigInteger('bundle_item_id')->nullable();
        });
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->bigInteger('bundle_item_id')->unsigned()->nullable();
            $table->unique(['shipment_id', 'basket_item_id', 'bundle_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('basket_items', 'bundle_item_id')) {
            Schema::table('basket_items', function (Blueprint $table) {
                $table->dropColumn('bundle_item_id');
            });
        }

        if (Schema::hasColumn('shipment_items', 'bundle_item_id')) {
            Schema::table('shipment_items', function (Blueprint $table) {
                $table->dropColumn('bundle_item_id');
                $table->unique(['shipment_id', 'basket_item_id']);
            });
        }
    }
}
