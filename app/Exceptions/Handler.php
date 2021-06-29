<?php

namespace App\Exceptions;

use Exception;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            $this->setUserToSentry();

            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    protected function setUserToSentry(): void
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

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }
}
