<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceiverColumnsToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receiver_name')->nullable()->after('customer_id');
            $table->string('receiver_phone')->nullable()->after('receiver_name');
            $table->string('receiver_email')->nullable()->after('receiver_phone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('receiver_name');
            $table->dropColumn('receiver_phone');
            $table->dropColumn('receiver_email');
        });
    }
}
