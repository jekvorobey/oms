<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddUnitPriceToBasketItems extends Migration
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
            $table->decimal('unit_price', 18, 4)->unsigned()->after('price')->nullable();
        });

        DB::table(self::TABLE_NAME)->select('id', 'qty', 'price')->orderBy('id')->each(function ($basketItem) {
            if ($basketItem->price != 0 && $basketItem->qty != 0) {
                DB::table(self::TABLE_NAME)
                    ->where('id', $basketItem->id)
                    ->update([
                        'unit_price' => (float) $basketItem->price / $basketItem->qty,
                    ]);
            }
        });
    }

    public function down()
    {
        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
}
