<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Orders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('customer_id')->unsigned()->nullable();
        
            $table->string('number');
            $table->decimal('cost', 18, 4);
            $table->tinyInteger('status')->unsigned()->default(1);
            $table->tinyInteger('reserve_status')->unsigned()->default(1);
            $table->tinyInteger('delivery_type')->unsigned()->default(1);
            $table->tinyInteger('delivery_method')->unsigned()->default(1);
            $table->dateTime('processing_time');
            $table->dateTime('delivery_time');
            $table->text('comment')->nullable();
        
            $table->timestamps();
        });
        
        Schema::create('baskets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('customer_id')->unsigned()->nullable();
            $table->bigInteger('order_id')->unsigned()->nullable();
        
            $table->timestamps();
    
            $table->foreign('order_id')->references('id')->on('orders');
        });
        
        Schema::create('basket_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('basket_id')->unsigned()->nullable();
            $table->bigInteger('offer_id')->unsigned()->nullable();
    
            $table->string('name');
            $table->decimal('qty', 18, 4);
            $table->decimal('price', 18, 4);
            $table->boolean('is_reserved')->default(false);
            $table->bigInteger('reserved_by')->nullable();
            $table->timestamp('reserved_at')->nullable();
        
            $table->timestamps();
    
            $table->foreign('basket_id')->references('id')->on('baskets');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('basket_items');
        Schema::dropIfExists('baskets');
        Schema::dropIfExists('orders');
    }
}
