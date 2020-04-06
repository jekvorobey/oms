<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFlagColumnsToShipments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_status')->default(1)->after('status');
            $table->dateTime('payment_status_at')->nullable()->after('payment_status');
            $table->boolean('is_canceled')->default(false)->after('payment_status_at');
            $table->dateTime('is_canceled_at')->nullable()->after('is_canceled');
            $table->boolean('is_problem')->default(false)->after('is_canceled_at');
            $table->dateTime('is_problem_at')->nullable()->after('is_problem');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('payment_status');
            $table->dropColumn('payment_status_at');
            $table->dropColumn('is_canceled');
            $table->dropColumn('is_canceled_at');
            $table->dropColumn('is_problem');
            $table->dropColumn('is_problem_at');
        });
    }
}
