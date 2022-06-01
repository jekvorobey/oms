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
            'is_postpaid' => true,
        ],
        [
            'name' => 'В кредит от pp.credit',
            'code' => 'creditpaid',
            'active' => true,
            'settings' => [
                'discount' => '14',
                'signingKD' => 'KO',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->data as $item) {
            PaymentMethod::query()->firstOrCreate([
                'code' => $item['code'],
            ], $item);
        }
    }
}
