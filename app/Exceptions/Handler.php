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

    public function render($request, Throwable $e): Response|JsonResponse|SymfonyResponse
    {
        if ($request->wantsJson()) {
            // Define the response
            $response = [
                'message' => 'Произошла ошибка',
            ];

            // If the app is in debug mode
            if (config('app.debug')) {
                // Add the exception class name, message and stack trace to response
                $response['exception'] = (new \ReflectionClass($e))->getShortName();
                $response['message'] = $e->getMessage();
                $response['trace'] = $e->getTrace();
            }

            // Default response of 400
            $status = 400;

            // If this exception is an instance of HttpException
            if ($this->isHttpException($e)) {
                // Grab the HTTP status code from the Exception
                $status = $e->getStatusCode();
                $response['message'] = $e->getMessage();
            }

            // Return a JSON response with the response array and status code
            return response()->json($response, $status);
        }

        return parent::render($request, $e);
    }
}
