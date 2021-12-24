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
            $table->boolean('is_fullpayment_receipt_sent')->default(false);
        });

        Payment::where('status', PaymentStatus::PAID)
            ->whereHas('order', fn($q) => $q->where('status', OrderStatus::DONE))
            ->update(['is_fullpayment_receipt_sent' => true]);
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
