<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

class AddBankTransferForLegalPaymentMethod extends Migration
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
