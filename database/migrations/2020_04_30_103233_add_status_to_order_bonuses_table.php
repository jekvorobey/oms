<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Order\OrderBonus;

class AddStatusToOrderBonusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_bonuses', function (Blueprint $table) {
            $table->tinyInteger('status')
                ->unsigned()
                ->default(OrderBonus::STATUS_ON_HOLD)
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
        Schema::table('order_bonuses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
