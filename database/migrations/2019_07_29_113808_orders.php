<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->integer('type')->unsigned();
            $table->boolean('is_belongs_to_order')->default(false);

            $table->timestamps();
        });

        Schema::create('basket_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('basket_id')->unsigned()->nullable();
            $table->bigInteger('offer_id')->unsigned()->nullable();
            $table->integer('type')->unsigned();
            $table->json('product');
            $table->string('name');
            $table->decimal('qty', 18, 4);
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('discount', 18, 4)->nullable();
            $table->decimal('cost', 18, 4)->default(0.0);

            $table->timestamps();

            $table->foreign('basket_id')->references('id')->on('baskets');
        });

        Schema::create('orders', function (Blueprint $table) {
            // generated
            $table->bigIncrements('id');
            $table->string('number');
            // identify
            $table->bigInteger('basket_id')->unsigned();
            $table->bigInteger('customer_id')->unsigned()->nullable();
            // prices & marketing
            // полная стоимость заказанных товаров
            $table->decimal('cost', 18, 4)->default(0.0);
            // итоговая стоимость заказа со всеми скидками и доставкой
            $table->decimal('price', 18, 4)->default(0.0);
            $table->decimal('delivery_cost', 18, 4)->default(0.0);

            $table->integer('spent_bonus')->default(0);
            $table->integer('added_bonus')->default(0);
            $table->string('promocode')->nullable();
            $table->json('certificates')->nullable();
            // delivery
            $table->tinyInteger('delivery_type')->unsigned();
            $table->text('delivery_comment')->nullable();
            // statuses
            $table->tinyInteger('status')->unsigned()->default(1);
            $table->dateTime('status_at')->nullable();
            $table->tinyInteger('payment_status', false, true)->default(1);
            $table->dateTime('payment_status_at')->nullable();
            $table->boolean('is_problem')->default(false);
            $table->dateTime('is_problem_at')->nullable();
            // management
            $table->text('manager_comment')->nullable();

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
            $table->tinyInteger('payment_method', false, true);
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
            $table->text('shipping_problem_comment')->nullable();

            $table->timestamps();
        });

        Schema::create('delivery', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('delivery_method')->unsigned();
            $table->tinyInteger('delivery_service')->unsigned();
            $table->tinyInteger('status', false, true)->default(1);
            $table->string('status_xml_id')->nullable();

            $table->string('xml_id')->nullable();
            $table->integer('tariff_id')->nullable();
            $table->integer('point_id')->nullable();
            $table->string('number');

            $table->json('delivery_address')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->string('receiver_email')->nullable();

            $table->decimal('cost', 18, 4)->default(0.0);
            $table->decimal('width', 18, 4)->default(0.0);
            $table->decimal('height', 18, 4)->default(0.0);
            $table->decimal('length', 18, 4)->default(0.0);
            $table->decimal('weight', 18, 4)->default(0.0);
            $table->dateTime('delivery_at')->nullable();
            $table->dateTime('status_at')->nullable();
            $table->dateTime('status_xml_id_at')->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('delivery_id')->unsigned();
            $table->bigInteger('merchant_id')->unsigned();
            $table->tinyInteger('delivery_service_zero_mile')->unsigned()->nullable();
            $table->bigInteger('store_id')->unsigned();
            $table->bigInteger('cargo_id')->unsigned()->nullable();
            $table->tinyInteger('status', false, true)->default(1);

            $table->string('number');
            $table->decimal('cost', 18, 4)->default(0.0);
            $table->decimal('width', 18, 4)->default(0.0);
            $table->decimal('height', 18, 4)->default(0.0);
            $table->decimal('length', 18, 4)->default(0.0);
            $table->decimal('weight', 18, 4)->default(0.0);
            $table->timestamp('required_shipping_at');
            $table->text('assembly_problem_comment')->nullable();

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
