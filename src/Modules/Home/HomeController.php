<?php

declare(strict_types=1);

namespace App\Modules\Home;

use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Dotenv\Dotenv;

/**
 * The root endpoint: the name and the status of the application, so that a
 * deployment can be checked with one request.
 *
 * A module of one class: it owns an endpoint instead of a table, so it has no
 * model, no inputs and no resource. An invokable handler: a route may name a
 * class with __invoke() instead of a [class, method] pair.
 */
final readonly class HomeController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::data($response, [
            'name' => Dotenv::string('APP_NAME', 'Solo'),
            'status' => 'ok',
        ]);
    }
}
