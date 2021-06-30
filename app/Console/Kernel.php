<?php

namespace App\Console;

use App\Console\Commands\ApproveBonus;
use App\Console\Commands\CancelExpiredOrders;
use App\Console\Commands\CargoExport;
use App\Console\Commands\CheckCourierCallsForCDEK;
use App\Console\Commands\CommitPayments;
use App\Console\Commands\NotifyPublicEvent;
use App\Console\Commands\SetWaitingStatus2Payment;
use App\Console\Commands\UpdateDeliveriesStatus;
use Greensight\Store\Dto\StoreDto;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(SetWaitingStatus2Payment::class)->everyMinute();
        $schedule->command(CancelExpiredOrders::class)->everyMinute();
        $schedule->command(UpdateDeliveriesStatus::class)->everyTenMinutes();
        $schedule->command(CommitPayments::class)->hourly();
        $schedule->command(ApproveBonus::class)->dailyAt('00:00');
        $schedule->command(NotifyPublicEvent::class)->dailyAt('00:00');
        $schedule->command(CheckCourierCallsForCDEK::class)->everyFiveMinutes();

        try {
            $this->cargoExportByStores($schedule);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        $this->load(__DIR__ . '/Commands/OneTime');

        require base_path('routes/console.php');
    }

    /**
     * Создание заявок на вызов курьера по складам
     */
    protected function cargoExportByStores(Schedule $schedule): void
    {
        $dayOfWeek = now()->format('N');
        /** @var Collection|StoreDto[] $stores */
        $stores = Cache::remember('stores_with_pickup_times', 60 * 60, function () {
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            $storeQuery = $storeService->newQuery()
                ->include('storePickupTime');
            $stores = $storeService->stores($storeQuery);
            return $stores->filter(function (StoreDto $store) {
                return !is_null($store->storePickupTime());
            });
        });

        foreach ($stores as $store) {
            foreach ($store->storePickupTime() as $pickupTime) {
                if ($pickupTime->day == $dayOfWeek) {
                    if ($pickupTime->cargo_export_time) {
                        try {
                            $schedule->command(CargoExport::class, [$store->id, $pickupTime->delivery_service])
                                ->at((new \DateTime($pickupTime->cargo_export_time))->format('H:i'));
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }
        }
    }
}
