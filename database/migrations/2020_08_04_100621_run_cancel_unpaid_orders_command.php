<?php

use App\Console\Commands\OneTime\CancelUnpaidOrders;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

class RunCancelUnpaidOrdersCommand extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Artisan::call(CancelUnpaidOrders::class);
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
