<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A write takes a JSON body, and a request that sends another format is a 415.
 * It is attached to a route rather than listed in config/middleware.php, so
 * DELETE /tasks/{id}, which has no body, does not need a Content-Type.
 */
final readonly class RequireJsonMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::isJson($request->getHeaderLine('Content-Type'))) {
            throw new HttpException(415, 'UNSUPPORTED_MEDIA_TYPE');
        }

        return $handler->handle($request);
    }

    /**
     * The media type, not the whole header: `application/json; charset=utf-8` is
     * JSON, and a parameter that merely mentions it — `text/plain; name=json` —
     * is not. A structured-suffix type such as `application/merge-patch+json`
     * carries JSON too.
     */
    private static function isJson(string $contentType): bool
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $mediaType === 'application/json'
            || $mediaType === 'text/json'
            || str_ends_with($mediaType, '+json');
    }
}
