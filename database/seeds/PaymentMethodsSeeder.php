<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    private array $data = [
        [
            'name' => 'Предоплата (онлайн)',
            'code' => 'prepaid',
            'active' => true,
        ],
        [
            'name' => 'Постоплата (Наличными или картой при получении)',
            'code' => 'postpaid',
            'active' => true,
        ],
        [
            'name' => 'В кредит от pp.credit',
            'code' => 'creditpaid',
            'active' => true,
        ],
    ];

    public function run(): void
    {
        foreach ($this->data as $item) {
            PaymentMethod::query()->updateOrCreate([
                'code' => $item['code'],
            ], $item);
        }
    }
}
