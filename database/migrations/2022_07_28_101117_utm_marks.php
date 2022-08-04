<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UtmMarks extends Migration
{
    private const FIELDS = ['utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_term'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (self::FIELDS as $field) {
                $table->string($field)->nullable();
            }
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
            foreach (self::FIELDS as $field) {
                $table->dropColumn($field);
            }
        });
    }
}
