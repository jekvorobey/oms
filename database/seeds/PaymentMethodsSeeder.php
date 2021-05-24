<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    public const availableMethods = [
        1 => [
            'name' => 'Сертификат подарочный',
            'code' => 'gift_certificate',
        ],
        2 => [
            'name' => 'Бонусный счет',
            'code' => 'bonus_balance',
        ],
        3 => [
            'name' => 'Банковская карта',
            'code' => 'credit_card',
        ],
        4 => [
            'name' => 'Google Pay / Apple Pay',
            'code' => 'mobile_acquiring',
        ],
        5 => [
            'name' => 'Пользовательский счет',
            'code' => 'internal_balance',
        ],
        6 => [
            'name' => 'Наличные или картой при получении',
            'code' => 'cash',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Защита от дубликатов: очистить таблицу перед заполнением //
        PaymentMethod::query()->truncate();

        foreach (self::availableMethods as $key => $value) {
            $record = new PaymentMethod();
            $record->id = $key;
            $record->name = $value['name'];
            $record->code = $value['code'];

            $record->save();
        }
    }
}
