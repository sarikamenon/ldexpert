<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SentryContext::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report only critical exceptions and custom business exceptions to Sentry
        $exceptions->report(function (Throwable $e) {

            // Don't report non-critical exceptions
            if (
                $e instanceof ValidationException
                || $e instanceof NotFoundHttpException
                || $e instanceof AccessDeniedHttpException
                || $e instanceof TooManyRequestsHttpException
            ) {
                return;
            }

            // Only report fatal/critical errors
            if (app()->bound('sentry') && ($e->getCode() >= 500 || str_contains($e->getMessage(), 'SQLSTATE'))) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
