<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderPromoCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_promo_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('promo_code_id')->unsigned();
            $table->string('name', 255);
            $table->string('code', 255);
            $table->tinyInteger('type')->unsigned();
            $table->bigInteger('discount_id')->unsigned()->nullable();
            $table->bigInteger('gift_id')->unsigned()->nullable();
            $table->bigInteger('bonus_id')->unsigned()->nullable();
            $table->boolean('is_personal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_promo_codes');
    }
}
