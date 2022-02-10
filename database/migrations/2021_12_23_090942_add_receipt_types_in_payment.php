<?php

use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReceiptTypesInPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('is_receipt_sent', 'is_prepayment_receipt_sent');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_fullpayment_receipt_sent')->default(false);
        });
        // Простановка флага для старых доставленных заказов
        Payment::where('status', PaymentStatus::PAID)
            ->whereHas('order', fn($q) => $q->where('status', OrderStatus::DONE))
            ->update([
                'is_prepayment_receipt_sent' => true,
                'is_fullpayment_receipt_sent' => true,
            ]);

        // Фикс для прода: Простановка флага для заказов с чеками по старой логике - чек при оформлении был сразу fullpayment
        if (app()->isProduction()) {
            Payment::whereIn('status', [PaymentStatus::HOLD, PaymentStatus::PAID])
                ->whereHas('order', fn($q) => $q->where('id', '<', 3211))
                ->update([
                    'is_prepayment_receipt_sent' => true,
                    'is_fullpayment_receipt_sent' => true,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('is_prepayment_receipt_sent', 'is_receipt_sent');
            $table->dropColumn('is_fullpayment_receipt_sent');
        });
    }
}
