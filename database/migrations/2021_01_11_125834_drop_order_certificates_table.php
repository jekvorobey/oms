<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropOrderCertificatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('certificates')->nullable()->after('added_bonus');
        });

        Schema::dropIfExists('order_certificates');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('order_certificates', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('card_id')->unsigned();
            $table->string('code', 255);
            $table->tinyInteger('status');
            $table->integer('amount');

            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('certificates');
        });
    }
}
