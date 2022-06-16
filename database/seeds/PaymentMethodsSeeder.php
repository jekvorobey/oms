<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    private array $data = [
        [
            'id' => PaymentMethod::PREPAID,
            'name' => 'Предоплата (онлайн)',
            'code' => 'prepaid',
            'active' => true,
        ],
        [
            'id' => PaymentMethod::POSTPAID,
            'name' => 'Постоплата (Наличными или картой при получении)',
            'code' => 'postpaid',
            'active' => true,
            'is_postpaid' => true,
            'is_need_create_payment' => false,
        ],
        [
            'id' => PaymentMethod::CREDITPAID,
            'name' => 'В кредит от pp.credit',
            'code' => 'creditpaid',
            'active' => true,
            'is_apply_discounts' => false,
            'is_need_create_payment' => false,
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
                'id' => $item['id'],
            ], $item);
        }
    }
}
