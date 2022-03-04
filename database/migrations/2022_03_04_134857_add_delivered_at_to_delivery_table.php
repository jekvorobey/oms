<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDeliveredAtToDeliveryTable extends Migration
{
    public function up(): void
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->dateTime('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
}
