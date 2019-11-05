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
        Schema::create('baskets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('customer_id')->unsigned()->nullable();

            $table->boolean('is_belongs_to_order')->default(false);

            $table->timestamps();
        });

        Schema::create('basket_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('basket_id')->unsigned()->nullable();
            $table->bigInteger('offer_id')->unsigned()->nullable();
            $table->bigInteger('store_id')->unsigned()->nullable();

            $table->string('name');
            $table->decimal('qty', 18, 4);
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('discount', 18, 4)->nullable();
            $table->decimal('cost', 18, 4)->default(0.0);

            $table->timestamps();

            $table->foreign('basket_id')->references('id')->on('baskets');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('basket_id')->unsigned();
            $table->bigInteger('customer_id')->unsigned()->nullable();
            $table->tinyInteger('status')->unsigned()->default(1);
            $table->tinyInteger('payment_status', false, true)->default(1);
            $table->tinyInteger('delivery_type')->unsigned();
            $table->tinyInteger('delivery_service')->unsigned();
            $table->tinyInteger('delivery_method')->unsigned();

            $table->string('number');
            $table->decimal('cost', 18, 4)->default(0.0);
            $table->text('manager_comment')->nullable();
            // delivery
            $table->decimal('delivery_cost', 18, 4)->default(0.0);
            $table->json('delivery_address');
            $table->text('delivery_comment')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->string('receiver_email')->nullable();

            $table->timestamps();

            $table->foreign('basket_id')->references('id')->on('baskets');
        });

        Schema::create('history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->tinyInteger('type')->unsigned();
            $table->jsonb('data')->nullable();

            $table->string('entity');
            $table->bigInteger('entity_id')->unsigned();

            $table->timestamps();
        });
    
        Schema::create('history_main_entity', function (Blueprint $table) {
            $table->bigIncrements('id');
    
            $table->bigInteger('history_id')->unsigned();
            $table->string('main_entity');
            $table->bigInteger('main_entity_id')->unsigned();
    
            $table->timestamps();
    
            $table->foreign('history_id')->references('id')->on('history');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->tinyInteger('payment_system', false, true);
            $table->tinyInteger('status', false, true)->default(1);

            $table->float('sum');
            $table->tinyInteger('type', false, true);
            $table->dateTime('payed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at');
            $table->json('data');

            $table->foreign('order_id')->references('id')->on('orders');
        });

        Schema::create('cargo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('merchant_id')->unsigned();
            $table->bigInteger('store_id')->unsigned();
            $table->tinyInteger('status', false, true)->default(1);
            $table->tinyInteger('delivery_service')->unsigned();

            $table->string('xml_id')->nullable();
            $table->decimal('width', 18, 4);
            $table->decimal('height', 18, 4);
            $table->decimal('length', 18, 4);
            $table->decimal('weight', 18, 4);

            $table->timestamps();
        });

        Schema::create('delivery', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('delivery_method')->unsigned();
            $table->tinyInteger('delivery_service')->unsigned();
            $table->tinyInteger('status', false, true)->default(1);

            $table->string('xml_id')->nullable();
            $table->string('number');
            $table->decimal('cost', 18, 4)->default(0.0);
            $table->decimal('width', 18, 4)->default(0.0);
            $table->decimal('height', 18, 4)->default(0.0);
            $table->decimal('length', 18, 4)->default(0.0);
            $table->decimal('weight', 18, 4)->default(0.0);
            $table->dateTime('delivery_at')->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('delivery_id')->unsigned();
            $table->bigInteger('merchant_id')->unsigned();
            $table->bigInteger('store_id')->unsigned();
            $table->bigInteger('cargo_id')->unsigned()->nullable();
            $table->tinyInteger('status', false, true)->default(1);

            $table->string('number');
            $table->decimal('cost', 18, 4)->default(0.0);
            $table->timestamp('required_shipping_at');

            $table->timestamps();

            $table->foreign('delivery_id')->references('id')->on('delivery');
            $table->foreign('cargo_id')->references('id')->on('cargo');
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('basket_item_id')->unsigned();

            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments');
            $table->foreign('basket_item_id')->references('id')->on('basket_items');

            $table->unique(['shipment_id', 'basket_item_id']);
        });

        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('package_id')->unsigned();
            $table->tinyInteger('status', false, true)->default(1);

            $table->decimal('width', 18, 4);
            $table->decimal('height', 18, 4);
            $table->decimal('length', 18, 4);
            $table->decimal('weight', 18, 4);
            $table->decimal('wrapper_weight', 18, 4);

            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments');
        });

        Schema::create('shipment_package_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_package_id')->unsigned();
            $table->bigInteger('basket_item_id')->unsigned();
            $table->decimal('qty', 18, 4);
            $table->bigInteger('set_by')->nullable();

            $table->timestamps();

            $table->foreign('shipment_package_id')->references('id')->on('shipment_packages');
            $table->foreign('basket_item_id')->references('id')->on('basket_items');

            $table->unique(['shipment_package_id', 'basket_item_id']);
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

        Schema::create('orders_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->text('text')->nullable();
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
        Schema::dropIfExists('shipment_package_items');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('cargo');
        Schema::dropIfExists('delivery');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders_comments');
        Schema::dropIfExists('orders_export');
        Schema::dropIfExists('history_main_entity');
        Schema::dropIfExists('history');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('basket_items');
        Schema::dropIfExists('baskets');
    }
}
