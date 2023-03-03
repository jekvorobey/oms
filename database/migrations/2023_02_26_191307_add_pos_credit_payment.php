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
        $paymentMethodCredit = PaymentMethod::query()->find(PaymentMethod::CREDITLINE_PAID)?->toArray();
        $paymentMethodPosCredit = $paymentMethodCredit;
        $paymentMethodPosCredit['id'] = PaymentMethod::POSCREDIT_PAID;
        $paymentMethodPosCredit['name'] = 'В рассрочку PosCredit';
        $paymentMethodPosCredit['code'] = 'poscredit_paid';
        $paymentMethodPosCredit['active'] = false;
        $paymentMethodPosCredit['button_text'] = '<p class="text-bold"  style="width: 100%;">В рассрочку (PosCredit)</p><p class="text-grey text-sm">Для оформления заявки потребуется паспорт</p>';

        PaymentMethod::query()->updateOrCreate([
            'id' => $paymentMethodPosCredit['id'],
        ], $paymentMethodPosCredit);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        PaymentMethod::query()->find(PaymentMethod::POSCREDIT_PAID)?->delete();
    }
};
