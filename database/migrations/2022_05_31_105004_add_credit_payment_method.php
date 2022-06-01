<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditPaymentMethod extends Migration
{
    public function up()
    {
        Artisan::call('db:seed', [
            '--class' => 'PaymentMethodsSeeder',
            '--force' => true,
        ]);
    }

    public function down()
    {
        //
    }
}
