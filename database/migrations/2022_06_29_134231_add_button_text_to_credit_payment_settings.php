<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddButtonTextToCreditPaymentSettings extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up()
    {
        DB::table(self::TABLE_NAME)
            ->where('id', PaymentMethod::CREDITPAID)
            ->update(['settings->button_text' => 'Оплатить кредитом']);

        DB::table(self::TABLE_NAME)
            ->where('id', PaymentMethod::B2B_SBERBANK)
            ->update([
                'settings' => json_encode([
                    'button_text' => 'Оплатить через Сбербанк Бизнес Онлайн',
                ]),
            ]);
    }

    public function down()
    {
        //
    }
}
