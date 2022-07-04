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
                                Предоплата (онлайн)
                            </div>

                            <div class="checkout-product-panel__item-payment-list checkout-product-panel__item-payment-list--w-full">
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <v-svg name="visa" width="40" height="24" />
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <v-svg name="mastercard" width="40" height="24" />
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <v-svg name="mir" width="40" height="24" />
                                </div>
                                <div class="checkout-product-panel__item-payment-list-item">
                                    <v-svg name="yandex" width="56" height="24" />
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
            'button_text' => 'Постоплата (Наличными или картой при получении)',
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
            'button_text' => '
                            <div class="text-bold checkout-product-panel__item-payment-title">
                                В кредит от pp.credit
                                <span class="text-sm" v-if="!method.is_available">(от 10 000 ₽)</span>
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
            'button_text' => 'СберБизнес (онлайн)',
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
