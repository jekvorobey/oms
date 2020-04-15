<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusToOrderPromoCodesTable extends Migration
{
    const PROMO_CODE_STATUS_ACTIVE = 4;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_promo_codes', function (Blueprint $table) {
            $table->tinyInteger('status')
                ->unsigned()
                ->default(self::PROMO_CODE_STATUS_ACTIVE)
                ->after('type');
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
            $table->dropColumn('status');
        });
    }
}
