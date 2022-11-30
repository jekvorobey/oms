<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->string('guid', 50)->nullable()->after('id');
        });
        Schema::table('shipments', static function (Blueprint $table) {
            $table->string('guid', 50)->nullable()->after('id');
        });
        Schema::table('payments', static function (Blueprint $table) {
            $table->string('guid', 50)->nullable()->after('id');
        });

        DB::table('orders')->update(['guid' => DB::raw("(SELECT @i:=UUID())")]);
        DB::table('shipments')->update(['guid' => DB::raw("(SELECT @i:=UUID())")]);
        DB::table('payments')->update(['guid' => DB::raw("(SELECT @i:=UUID())")]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('guid');
        });
        Schema::table('shipments', static function (Blueprint $table) {
            $table->dropColumn('guid');
        });
        Schema::table('payments', static function (Blueprint $table) {
            $table->dropColumn('guid');
        });
    }
};
