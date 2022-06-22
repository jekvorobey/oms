<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsApplyDiscountsToPaymentMethodsTable extends Migration
{
    public function up()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->renameColumn('is_need_payment', 'is_need_create_payment');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_apply_discounts')->default(true)->after('is_need_create_payment');
        });

        Artisan::call('db:seed', [
            '--class' => 'PaymentMethodsSeeder',
            '--force' => true,
        ]);
    }

    public function down()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->renameColumn('is_need_create_payment', 'is_need_payment');
            $table->removeColumn('is_apply_discounts');
        });
    }
}
