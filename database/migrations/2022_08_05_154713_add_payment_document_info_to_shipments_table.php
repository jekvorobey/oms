<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPaymentDocumentInfoToShipmentsTable extends Migration
{
    public function up()
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dateTime('payment_document_date')->nullable()->after('payment_status_at');
            $table->string('payment_document_number')->nullable()->after('payment_status_at');
        });
    }

    public function down()
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('payment_document_number');
            $table->dropColumn('payment_document_date');
        });
    }
}
