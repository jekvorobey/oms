<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderReturnItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_return_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_return_id')->unsigned();
            $table->bigInteger('basket_item_id')->unsigned();
            $table->bigInteger('offer_id')->unsigned()->nullable();
            $table->bigInteger('referrer_id')->unsigned()->nullable();
            $table->bigInteger('bundle_id')->unsigned()->nullable();
            $table->integer('type')->unsigned();

            $table->json('product')->nullable();
            $table->string('name');
            $table->decimal('qty', 18, 4)->default(1);
            $table->decimal('price', 18, 4)->default(0.0);
            $table->decimal('commission', 18, 4)->default(0.0);

            $table->timestamps();

            $table->foreign('order_return_id')->references('id')->on('order_returns');
            $table->foreign('basket_item_id')->references('id')->on('basket_items');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_return_items');
    }
}
