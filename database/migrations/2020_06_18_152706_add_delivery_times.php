<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryTimes extends Migration
{
    public function up()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->string('delivery_time_code')->nullable()->after('delivery_at');
            $table->time('delivery_time_start')->nullable()->after('delivery_time_code');
            $table->time('delivery_time_end')->nullable()->after('delivery_time_start');
        });
    }

    public function down()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->dropColumn('delivery_time_end');
            $table->dropColumn('delivery_time_start');
            $table->dropColumn('delivery_time_code');
        });
    }
}
