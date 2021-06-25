<?php

namespace App\Providers;

use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;

class SentryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Integration::configureScope(static function (Scope $scope): void {
            try {
                $request = resolve(RequestInitiator::class);

                $scope->setUser([
                    'id' => $request->userId(),
                ]);
            } catch (\Throwable $exception) {
                //
            }
        });
    }
}
