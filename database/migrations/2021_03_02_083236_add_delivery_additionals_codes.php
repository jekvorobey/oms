<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDeliveryAdditionalsCodes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('xml_id');
            $table->string('tracknumber')->nullable()->after('xml_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery', function (Blueprint $table) {
            $table->dropColumn('barcode');
            $table->dropColumn('tracknumber');
        });
    }
}
