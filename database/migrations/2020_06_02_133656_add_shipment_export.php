<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShipmentExport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shipment_export', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('merchant_integration_id')->unsigned();
            $table->string('shipment_xml_id')->nullable();
            $table->integer('err_code')->nullable();
            $table->string('err_message')->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments');
            $table->unique(['shipment_id', 'merchant_integration_id', 'shipment_xml_id'], 'shipment_merchant_xml_unique');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shipment_export');
    }
}
