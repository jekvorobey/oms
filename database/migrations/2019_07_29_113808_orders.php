<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Class Orders
 */
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
            $table->text('manager_comment')->nullable();
            $table->tinyInteger('payment_status', false, true)->default(1);
            // delivery
            $table->tinyInteger('delivery_type')->unsigned()->default(1);
            $table->tinyInteger('delivery_method')->unsigned()->default(1);
            $table->json('delivery_address');
            $table->text('delivery_comment')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->string('receiver_email')->nullable();

            $table->timestamps();
        });

        Schema::create('orders_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->tinyInteger('type')->unsigned()->nullable();
            $table->jsonb('data')->nullable();

            $table->string('entity')->nullable();
            $table->bigInteger('entity_id')->unsigned()->nullable();

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
            $table->decimal('price', 18, 4)->nullable();
            $table->boolean('is_reserved')->default(false);
            $table->bigInteger('reserved_by')->nullable();
            $table->timestamp('reserved_at')->nullable();

            $table->timestamps();

            $table->foreign('basket_id')
                ->references('id')
                ->on('baskets');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->float('sum');
            $table->tinyInteger('status', false, true)->default(1);
            $table->tinyInteger('type', false, true);
            $table->tinyInteger('payment_system', false, true);
            $table->dateTime('payed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at');

            $table->json('data');

            $table->foreign('order_id')->references('id')->on('orders');
        });
        
        Schema::create('cargo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('status', false, true)->default(1);
    
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('length')->nullable();
            $table->integer('weight')->nullable();
            
            $table->timestamps();
        });
    
        Schema::create('shipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('cargo_id')->unsigned()->nullable();
            $table->json('items');
            $table->dateTime('delivery_at')->nullable();
            $table->tinyInteger('status', false, true)->default(1);
            $table->timestamps();
        
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('cargo_id')->references('id')->on('cargo');
        });
        
        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->bigInteger('shipment_id')->unsigned();
            $table->tinyInteger('status', false, true)->default(1);
            $table->json('items');
    
            $table->integer('width');
            $table->integer('height');
            $table->integer('length');
            $table->integer('weight');
            $table->integer('wrapper_weight');
            
            $table->timestamps();
            
            $table->foreign('shipment_id')->references('id')->on('shipments');
        });
        
        Schema::create('orders_export', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('merchant_integration_id')->unsigned();
            $table->string('order_xml_id');
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unique(['merchant_integration_id', 'order_xml_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders_export');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('cargo');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('basket_items');
        Schema::dropIfExists('baskets');
        Schema::dropIfExists('orders_history');
        Schema::dropIfExists('orders');
    }
}
