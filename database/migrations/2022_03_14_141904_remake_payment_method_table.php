<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemakePaymentMethodTable extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up()
    {
        Schema::dropIfExists(self::TABLE_NAME);

        Schema::create(self::TABLE_NAME, function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code');
            $table->boolean('active')->default(false);
            $table->boolean('is_prepaid')->default(false);
            $table->timestamps();
        });

        Artisan::call('db:seed', [
            '--class' => 'PaymentMethodsSeeder',
            '--force' => true,
        ]);
    }

    public function down()
    {
        Schema::dropIfExists(self::TABLE_NAME);
    }
}
