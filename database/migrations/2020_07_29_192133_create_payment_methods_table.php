<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->tinyInteger('id')->unsigned()->primary();
            $table->string('name');
            $table->string('code');
            $table->boolean('accept_prepaid')->default(true);
            $table->boolean('accept_virtual')->default(true);
            $table->boolean('accept_real')->default(true);
            $table->boolean('accept_postpaid')->default(true);
            $table->decimal('covers', 3, 2)->default(1.00);
            $table->decimal('max_limit', 18, 4)->default(500000.0);
            $table->json('excluded_payment_methods')->nullable();
            $table->json('excluded_regions')->nullable();
            $table->json('excluded_delivery_services')->nullable();
            $table->json('excluded_offer_statuses')->nullable();
            $table->json('excluded_customers')->nullable();
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('payment_methods');
    }
}
