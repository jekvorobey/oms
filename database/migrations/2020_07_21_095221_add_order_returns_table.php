<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderReturnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('customer_id')->unsigned();
            $table->integer('type')->unsigned();

            $table->string('number');
            $table->decimal('price', 18, 4)->default(0.0);
            $table->decimal('commission', 18, 4)->default(0.0);
            $table->tinyInteger('status')->unsigned()->default(1);
            $table->dateTime('status_at')->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_returns');
    }
}
