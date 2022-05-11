<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTypeErrMessageToShipmentExportTable extends Migration
{
    public function up()
    {
        Schema::table('shipment_export', function (Blueprint $table) {
            $table->text('err_message')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('shipment_export', function (Blueprint $table) {
            $table->string('err_message')->nullable()->change();
        });
    }
}
