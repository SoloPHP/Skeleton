<?php

declare(strict_types=1);

namespace App\Home;

use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Dotenv\Dotenv;

/**
 * An invokable handler: a route may name a class with __invoke() instead of a
 * [class, method] pair.
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
