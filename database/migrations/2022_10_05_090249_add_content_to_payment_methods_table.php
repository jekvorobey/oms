<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Payment\PaymentMethod;

class AddContentToPaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    private array $data = [

    ];

    public function up()
    {
        PaymentMethod::query()->where('id', PaymentMethod::CREDITPAID)->update([
            'settings' => json_encode([
                'is_fixed_discount' => false,
                'discount' => '14',
                'is_displayed_in_catalog' => false,
                'is_displayed_in_mk' => false,
                'installment_period' => '12',
                'signingKD' => 'KO',
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        PaymentMethod::query()->where('id', PaymentMethod::CREDITPAID)->update([
            'settings' => json_encode([
                'is_fixed_discount' => false,
                'discount' => '14',
                'signingKD' => 'KO',
            ]),
        ]);
    }
}
