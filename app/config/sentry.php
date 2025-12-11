<?php

declare(strict_types=1);

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // The release version of your application
    // Example with dynamic git hash: 'release' => trim(exec('git --git-dir ' . base_path('.git') . ' log -1 --format=%H')),
    'release' => env('SENTRY_RELEASE'),

    // When left empty or `null` the Laravel environment will be used
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample-rate
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // @see: https://docs.sentry.io/platforms/php/profiling/
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send-default-pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#max-breadcrumbs
    'max_breadcrumbs' => env('SENTRY_MAX_BREADCRUMBS', 50),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore-exceptions
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class,
    ],

];
