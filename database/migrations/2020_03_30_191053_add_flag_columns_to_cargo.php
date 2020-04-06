<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFlagColumnsToCargo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cargo', function (Blueprint $table) {
            $table->boolean('is_canceled')->default(false)->after('status');
            $table->dateTime('is_canceled_at')->nullable()->after('is_canceled');
            $table->boolean('is_problem')->default(false)->after('is_canceled_at');
            $table->dateTime('is_problem_at')->nullable()->after('is_problem');
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
            $table->dropColumn('is_canceled');
            $table->dropColumn('is_canceled_at');
            $table->dropColumn('is_problem');
            $table->dropColumn('is_problem_at');
        });
    }
}
