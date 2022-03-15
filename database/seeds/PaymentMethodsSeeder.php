<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    private array $data = [
        [
            'name' => 'Предоплата (онлайн)',
            'code' => 'prepaid',
        ],
        [
            'name' => 'Постоплата (Наличными или картой при получении)',
            'code' => 'postpaid',
            'is_postpaid' => true,
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
