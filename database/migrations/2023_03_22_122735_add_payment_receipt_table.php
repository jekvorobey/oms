<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('guid', 50)->nullable();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('payment_id')->unsigned();
            $table->float('sum');
            $table->dateTime('payed_at')->nullable();
            $table->tinyInteger('receipt_type', false, true)->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->tinyInteger('status', false, true)->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('payment_id')->references('id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
