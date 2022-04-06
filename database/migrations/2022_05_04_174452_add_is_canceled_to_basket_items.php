<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsCanceledToBasketItems extends Migration
{
    private const TABLE_NAME = 'basket_items';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->unsignedBigInteger('return_reason_id')->after('is_returned')->nullable();
            $table->unsignedBigInteger('canceled_by')->after('is_returned')->nullable();
            $table->boolean('is_canceled')->after('is_returned')->default(false);
            $table->decimal('qty_canceled', 18, 4)
                ->unsigned()->after('is_returned')->nullable();
        });
    }

    public function down()
    {
        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->dropColumn('is_canceled');
            $table->dropColumn('qty_canceled');
            $table->dropColumn('canceled_by');
        });
    }
}
