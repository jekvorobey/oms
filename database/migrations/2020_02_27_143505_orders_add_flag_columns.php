<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OrdersAddFlagColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_canceled')->default(false)->after('is_problem_at');
            $table->dateTime('is_canceled_at')->nullable()->after('is_canceled');
            $table->boolean('is_require_check')->default(false)->after('is_canceled_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_canceled');
            $table->dropColumn('is_canceled_at');
            $table->dropColumn('is_require_check');
        });
    }
}
