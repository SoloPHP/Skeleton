<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\HttpException;
use App\Http\JsonResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Solo\Application\Exception\RoutingException;
use Solo\Input\Exceptions\ValidationException;
use Throwable;

/**
 * The one place where exceptions become responses. Client errors are answered
 * with their code; anything else is a 500 that is logged, and its details reach
 * the response only in debug mode.
 */
final readonly class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->render($e, $request);
        }
    }

    private function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responses->createResponse();

        return match (true) {
            $e instanceof ValidationException => JsonResponse::error(
                $response,
                'VALIDATION_FAILED',
                422,
                ['fields' => $e->getErrors()],
            ),
            $e instanceof HttpException => JsonResponse::error($response, $e->errorCode, $e->status),
            // 404, 405 with its Allow header, 400 for a malformed route parameter
            $e instanceof RoutingException => $e->applyTo(
                JsonResponse::error($response, self::routingCode($e->getStatusCode()), $e->getStatusCode()),
            ),
            default => $this->serverError($e, $request, $response),
        };
    }

    private function serverError(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->logger->error('{method} {path}: {exception}', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'exception' => $e,
        ]);

        $details = $this->debug ? ['debug' => [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'at' => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ]] : [];

        return JsonResponse::error($response, 'SERVER_ERROR', 500, $details);
    }

    private static function routingCode(int $status): string
    {
        return match ($status) {
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            default => 'BAD_REQUEST',
        };
    }
}
