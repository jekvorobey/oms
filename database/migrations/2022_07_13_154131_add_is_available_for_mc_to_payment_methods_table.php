<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AddIsAvailableForMcToPaymentMethodsTable extends Migration
{
    public function up()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_available_for_mc')->default(false)->after('is_apply_discounts');
        });

        Artisan::call('db:seed', [
            '--class' => 'PaymentMethodsSeeder',
            '--force' => true,
        ]);
    }

    public function down()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->removeColumn('is_available_for_mc');
        });
    }
}
