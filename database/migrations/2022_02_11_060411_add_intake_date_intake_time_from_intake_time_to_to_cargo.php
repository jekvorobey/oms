<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIntakeDateIntakeTimeFromIntakeTimeToToCargo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cargo', function (Blueprint $table) {
            $table->date('intake_date')->nullable();
            $table->time('intake_time_from')->nullable();
            $table->time('intake_time_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cargo', function (Blueprint $table) {
            $table->dropColumn([
                'intake_date',
                'intake_time_from',
                'intake_time_to',
            ]);
        });
    }
}
