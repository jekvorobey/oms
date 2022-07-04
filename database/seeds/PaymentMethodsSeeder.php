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
            'button_text' => '<div class="text-bold checkout-product-panel__item-payment-title">
                                Онлайн оплата
                            </div>

                            <div class="checkout-product-panel__item-payment-list checkout-product-panel__item-payment-list--w-full">
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <svg class="icon" width="40" height="24"><use xlink:href="#icon-visa"></use></svg>
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <svg class="icon" width="40" height="24"><use xlink:href="#icon-mastercard"></use></svg>
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <svg class="icon" width="40" height="24"><use xlink:href="#icon-mir"></use></svg>
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <svg class="icon" width="56" height="24"><use xlink:href="#icon-yandex"></use></svg>
                                </div>
                            </div>',
        ],
        [
            'id' => PaymentMethod::POSTPAID,
            'name' => 'Постоплата (Наличными или картой при получении)',
            'code' => 'postpaid',
            'active' => true,
            'is_postpaid' => true,
            'is_need_create_payment' => false,
            'button_text' => 'Наличными или картой при получении',
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
            'min_available_price' => 10000,
            'button_text' => '
                            <div class="text-bold checkout-product-panel__item-payment-title">
                                В рассрочку
                            </div>
                            <div class="checkout-product-panel__item-payment">
                                Для оформления заявки на кредит потребуется паспорт
                            </div>',
        ],
        [
            'id' => PaymentMethod::B2B_SBERBANK,
            'name' => 'СберБизнес (онлайн)',
            'code' => 'b2b_sberbank',
            'active' => true,
            'is_need_create_payment' => true,
            'button_text' => '<svg class="icon" width="251" height="60"><use xlink:href="#icon-b2b-sberbank"></use></svg>',
            'min_available_price' => 10000,
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
