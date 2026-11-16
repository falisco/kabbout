<?php

declare(strict_types=1);

namespace Kabbout\Api\UserInterface\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Every error of the API is answered in JSON, in the one shape the bridge already uses:
 * `{ "error": { "code", "message" } }`. This server only answers under /api: no HTML page has a reader.
 * The message is the name of the status, never the text of an exception: that text is for the log.
 */
#[AsController]
final readonly class ErrorController
{
    private const array CODES = [
        Response::HTTP_BAD_REQUEST => 'bad_request',
        Response::HTTP_NOT_FOUND => 'not_found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
        Response::HTTP_UNPROCESSABLE_ENTITY => 'unprocessable',
        Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
    ];

    public function __invoke(\Throwable $exception): JsonResponse
    {
        $http = $exception instanceof HttpExceptionInterface;
        $status = $http ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $code = self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'client_error');

        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => Response::$statusTexts[$status] ?? 'Error']],
            $status,
            [...($http ? $exception->getHeaders() : []), 'Cache-Control' => 'no-store'],
        );
    }
}
