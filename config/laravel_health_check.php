<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Health Checkers
    |
    | Set checkers active on the project.
    |--------------------------------------------------------------------------
    |
    */
    'checkers' => [
        Letsgoi\HealthCheck\Checkers\AppKeyChecker::class,
        Letsgoi\HealthCheck\Checkers\DatabaseConnectionChecker::class,
        // Letsgoi\HealthCheck\Checkers\DatabaseMigrationsChecker::class,
        // Letsgoi\HealthCheck\Checkers\DebugChecker::class,
        Letsgoi\HealthCheck\Checkers\EnvFileChecker::class,
        Letsgoi\HealthCheck\Checkers\WritablePathsChecker::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Endpoint
    |
    | Enable/disable endpoint, set endpoint path and configure the health check
    | healthy message returned.
    |--------------------------------------------------------------------------
    |
    */
    'endpoint' => [
        'enabled' => env('HEALTH_CHECK_ENDPOINT_ENABLED', true),

        'path' => env('HEALTH_CHECK_ENDPOINT_PATH', '/health-check'),

        'healthy_message' => env('HEALTH_CHECK_ENDPOINT_HEALTHY_MESSAGE', 'Healthy'),
    ],
];
