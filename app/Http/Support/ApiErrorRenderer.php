<?php

namespace App\Http\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiErrorRenderer
{
    public static function render(Throwable $exception, Request $request): ?array
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $requestId = $request->attributes->get('request_id');

        if ($exception instanceof ValidationException) {
            return [
                'message' => self::envelope(
                    'validation_error',
                    'The request is invalid.',
                    $exception->errors(),
                    $requestId,
                ),
                'status' => $exception->status,
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [
                'message' => self::envelope(
                    'unauthenticated',
                    'Authentication is required.',
                    null,
                    $requestId,
                ),
                'status' => 401,
            ];
        }

        if ($exception instanceof AuthorizationException) {
            return [
                'message' => self::envelope(
                    'forbidden',
                    'You do not have permission to perform this action.',
                    null,
                    $requestId,
                ),
                'status' => 403,
            ];
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return [
                'message' => self::envelope(
                    'not_found',
                    'The requested resource was not found.',
                    null,
                    $requestId,
                ),
                'status' => 404,
            ];
        }

        if ($exception instanceof HttpException) {
            $code = match ($exception->getStatusCode()) {
                400 => 'bad_request',
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                422 => 'validation_error',
                429 => 'too_many_requests',
                default => 'http_error',
            };

            return [
                'message' => self::envelope(
                    $code,
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'An error occurred.',
                    null,
                    $requestId,
                ),
                'status' => $exception->getStatusCode(),
            ];
        }

        if (config('app.debug')) {
            return [
                'message' => self::envelope(
                    'internal_error',
                    $exception->getMessage(),
                    [
                        'exception' => class_basename($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ],
                    $requestId,
                ),
                'status' => 500,
            ];
        }

        return [
            'message' => self::envelope(
                'internal_error',
                'An unexpected error occurred.',
                null,
                $requestId,
            ),
            'status' => 500,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>
     */
    public static function envelope(
        string $code,
        string $message,
        ?array $details,
        mixed $requestId,
    ): array {
        return [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => is_string($requestId) ? $requestId : null,
            ], fn ($value) => $value !== null),
        ];
    }
}
