<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception raised under /api as one predictable JSON envelope.
 *
 * Clients only ever receive:
 *
 *     { "message": string, "errors"?: object }
 *
 * Framework internals - the exception class, file, line and stack trace - are
 * never part of a 4xx response. A 5xx response carries a nested "debug" block
 * only while APP_DEBUG is on, so a crash can still be diagnosed locally without
 * the shape of the production contract ever changing.
 */
class ApiExceptionRenderer
{
    /**
     * Hook the API rendering policy into the application's exception handler.
     */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            // Everything else falls back to Laravel's own rendering.
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return self::render($e);
        });
    }

    private static function render(Throwable $e): Response
    {
        // An exception that already carries a response owns its own payload.
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        [$status, $message, $errors] = self::describe($e);

        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($status >= 500 && config('app.debug')) {
            $payload['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        $response = response()->json($payload, $status);

        // Keep framework headers such as Allow (405) and Retry-After (429).
        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }

    /**
     * Map an exception to a status code, a human message and optional field errors.
     *
     * @return array{0: int, 1: string, 2: array<string, array<int, string>>|null}
     */
    private static function describe(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [$e->status, $e->getMessage(), $e->errors()];
        }

        if ($e instanceof AuthenticationException) {
            return [401, 'Unauthenticated.', null];
        }

        if ($e instanceof AuthorizationException) {
            return [403, $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.', null];
        }

        if ($e instanceof ModelNotFoundException) {
            return [404, 'The requested resource was not found.', null];
        }

        if ($e instanceof NotFoundHttpException) {
            return [404, self::notFoundMessage($e), null];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return [
                $status,
                $e->getMessage() !== '' ? $e->getMessage() : (HttpResponse::$statusTexts[$status] ?? 'Error'),
                null,
            ];
        }

        return [500, 'Server Error.', null];
    }

    /**
     * Tell a missing record apart from a missing endpoint.
     */
    private static function notFoundMessage(NotFoundHttpException $e): string
    {
        return $e->getPrevious() instanceof ModelNotFoundException
            ? 'The requested resource was not found.'
            : 'The requested endpoint was not found.';
    }
}
