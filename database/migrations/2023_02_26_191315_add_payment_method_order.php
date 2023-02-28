<?php

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLE_NAME = 'payment_methods';

    public function up(): void
    {
        Schema::table(self::TABLE_NAME, static function (Blueprint $table) {
            $table->integer('sort')->after('settings')->nullable();
        });

        PaymentMethod::query()->update([
            'sort' => DB::raw('`id`'),
        ]);
    }

    public function down(): void
    {
        Schema::table(self::TABLE_NAME, static function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
