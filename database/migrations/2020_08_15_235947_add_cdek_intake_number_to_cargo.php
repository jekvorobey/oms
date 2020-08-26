<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCdekIntakeNumberToCargo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cargo', function (Blueprint $table) {
            $table->string('cdek_intake_number')->after('delivery_service')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cargo', function (Blueprint $table) {
            $table->dropColumn('cdek_intake_number');
        });
    }
}
