<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;

class AddButtonTextAndAvailablePriceToPaymentMethodsTable extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('button_text')->after('is_apply_discounts')->nullable();
            $table->float('max_available_price')->after('is_apply_discounts')->nullable();
            $table->float('min_available_price')->after('is_apply_discounts')->nullable();
        });

        Artisan::call('db:seed', [
            '--class' => 'PaymentMethodsSeeder',
            '--force' => true,
        ]);
    }

    public function down()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->removeColumn('button_text');
            $table->removeColumn('min_available_price');
            $table->removeColumn('max_available_price');
        });
    }
}
