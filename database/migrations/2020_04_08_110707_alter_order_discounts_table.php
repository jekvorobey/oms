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
            $table->bigInteger('discount_id')->nullable()->unsigned()->after('order_id');
            $table->string('name')->nullable()->after('discount_id');
            $table->tinyInteger('type')->nullable()->unsigned()->after('name');
            $table->integer('change')->nullable()->unsigned()->after('type');
            $table->bigInteger('merchant_id')->nullable()->unsigned()->after('change');
            $table->boolean('promo_code_only')->nullable()->after('merchant_id');
            $table->boolean('visible_in_catalog')->nullable()->after('promo_code_only');
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
        });

        Schema::table('order_discounts', function (Blueprint $table) {
            $table->json('discounts')->nullable()->after('order_id');
        });
    }
}
