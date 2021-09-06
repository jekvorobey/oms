<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Order\OrderDiscount;

class AlterOrderDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        OrderDiscount::query()->truncate();
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->bigInteger('discount_id')->unsigned()->after('order_id')->default(0);
            $table->string('name')->after('discount_id')->default('');
            $table->tinyInteger('type')->unsigned()->after('name')->default(0);
            $table->integer('change')->unsigned()->after('type')->default(0);
            $table->bigInteger('merchant_id')->unsigned()->nullable()->after('change');
            $table->boolean('promo_code_only')->after('merchant_id')->default(0);
            $table->boolean('visible_in_catalog')->after('promo_code_only')->default(0);
            $table->json('items')->nullable()->after('visible_in_catalog');
        });

        Schema::table('order_discounts', function (Blueprint $table) {
            $table->dropColumn('discounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->dropColumn('discount_id');
            $table->dropColumn('name');
            $table->dropColumn('type');
            $table->dropColumn('change');
            $table->dropColumn('merchant_id');
            $table->dropColumn('promo_code_only');
            $table->dropColumn('visible_in_catalog');
            $table->dropColumn('items');

            $table->json('discounts')->nullable()->after('order_id');
        });
    }
}
