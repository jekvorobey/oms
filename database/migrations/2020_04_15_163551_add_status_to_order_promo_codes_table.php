<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Order\OrderPromoCode;

class AddStatusToOrderPromoCodesTable extends Migration
{
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
                ->default(OrderPromoCode::STATUS_ACTIVE)
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
