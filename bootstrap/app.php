<?php

declare(strict_types=1);

use App\Exceptions\IllegalStateTransitionException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redact sensitive inputs from request logging & flashing
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Don't flash sensitive input to the session on validation errors
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            'secret',
            'key',
            'card_number',
            'cvv',
        ]);

        // Graceful handling for state machine transition violations
        $exceptions->render(function (IllegalStateTransitionException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Illegal State Transition',
                    'message' => $e->getMessage(),
                    'model' => $e->model,
                    'from' => $e->from->value,
                    'to' => $e->to->value,
                ], 422);
            }

            return null; // fall back to standard rendering
        });

        // Sentry-ready reporting hook
        $exceptions->report(function (Throwable $e): void {
            if (env('SENTRY_LARAVEL_DSN') && function_exists('app') && app()->bound('sentry')) {
                /** @phpstan-ignore-next-line */
                app('sentry')->captureException($e);
            }
        });
    })->create();
