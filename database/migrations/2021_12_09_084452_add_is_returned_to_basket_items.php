<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsReturnedToBasketItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('basket_items', function (Blueprint $table) {
            $table->boolean('is_returned')->default(false);
        });
    }

    public function down()
    {
        Schema::table('basket_items', function (Blueprint $table) {
            $table->dropColumn('is_returned');
        });
    }
}
