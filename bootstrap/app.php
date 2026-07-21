<?php

use App\Http\Support\ApiErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(prepend: [
            \App\Http\Middleware\AssignRequestId::class,
        ]);

        $middleware->alias([
            'request.id' => \App\Http\Middleware\AssignRequestId::class,
            'tenant.context' => \App\Http\Middleware\ResolveTenantEnvironment::class,
            'auth.api_or_sanctum' => \App\Http\Middleware\AuthenticateApiKeyOrSanctum::class,
            'idempotency' => \App\Http\Middleware\EnforceIdempotencyKey::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            $payload = ApiErrorRenderer::render($e, $request);

            if ($payload === null) {
                return null;
            }

            return response()->json($payload['message'], $payload['status']);
        });
    })
    ->create();
