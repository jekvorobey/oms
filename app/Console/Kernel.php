<?php

namespace App\Console;

use App\Console\Commands\CancelExpiredOrders;
use App\Console\Commands\CargoExport;
use App\Console\Commands\UpdateDeliveriesStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(CancelExpiredOrders::class)->everyTenMinutes();
        $schedule->command(UpdateDeliveriesStatus::class)->everyTenMinutes();
        //todo CargoExport schedule
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
