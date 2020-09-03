<?php

use App\Console\Commands\OneTime\FixPublicEventOrdersStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

class RunFixPublicEventOrdersStatusCommand extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Artisan::call(FixPublicEventOrdersStatus::class);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
