<?php

namespace App\Exceptions;

use Throwable;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Http\JsonResponse;

class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && $this->shouldReport($e)) {
                $this->setUserToSentry();

                app('sentry')->captureException($e);
            }
        });
    }

    protected function setUserToSentry(): void
    {
        Integration::configureScope(static function (Scope $scope): void {
            try {
                $request = resolve(RequestInitiator::class);
                $scope->setUser([
                    'id' => $request->userId(),
                ]);
            } catch (\Throwable) {
                //
            }
        });
    }
}
