<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $paymentMethodOnline = PaymentMethod::query()->find(PaymentMethod::PREPAID)?->toArray();
        $paymentMethodSbp = $paymentMethodOnline;
        $paymentMethodSbp['id'] = PaymentMethod::SBP_RAIFFEISEN;
        $paymentMethodSbp['name'] = 'Оплата СБП Raiffeisen';
        $paymentMethodSbp['code'] = 'sbp_raiffeisen';
        $paymentMethodSbp['active'] = true;
        $paymentMethodSbp['is_need_create_payment'] = true;
        $paymentMethodSbp['button_text'] = '<div class="text-bold checkout-product-panel__item-payment-title">Оплата СБП Raiffeisen</div><div class="checkout-product-panel__item-payment-list checkout-product-panel__item-payment-list--w-full"><div class="checkout-product-panel__item-payment-list-item"><svg class="icon" width="30" height="24"><use xlink:href="#icon-sbp"></use></svg></div></div>';

        PaymentMethod::query()->updateOrCreate([
            'id' => $paymentMethodSbp['id'],
        ], $paymentMethodSbp);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        PaymentMethod::query()->find(PaymentMethod::SBP_RAIFFEISEN)?->delete();
    }
};
