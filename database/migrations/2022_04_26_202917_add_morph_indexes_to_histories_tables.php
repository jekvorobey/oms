<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMorphIndexesToHistoriesTables extends Migration
{
    public function up()
    {
        Schema::table('history', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::table('history_main_entity', function (Blueprint $table) {
            $table->index(['main_entity_type', 'main_entity_id']);
        });
    }

    public function down()
    {
        Schema::table('history', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
        });

        Schema::table('history_main_entity', function (Blueprint $table) {
            $table->dropIndex(['main_entity_type', 'main_entity_id']);
        });
    }
}
