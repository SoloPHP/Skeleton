<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Migrator;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Solo\Application\Application;

/**
 * Feature tests send requests through the whole application, middleware and
 * routing included, with Application::handle(): no server, no emitting.
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        // A new application per test, so each one gets a new in-memory database
        $app = require __DIR__ . '/../bootstrap/app.php';
        self::assertInstanceOf(Application::class, $app);
        $this->app = $app;

        $this->app->container->get(Migrator::class)->run();
    }

    /** @param array<array-key, mixed>|string|null $body An array is sent as JSON, a string as it is */
    protected function request(string $method, string $uri, array|string|null $body = null): ResponseInterface
    {
        $request = new ServerRequest($method, $uri);

        // A PSR-7 request does not parse its own query string: the server request creator does
        parse_str($request->getUri()->getQuery(), $query);
        $request = $request->withQueryParams($query);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Stream::create(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR)));
        }

        return $this->app->handle($request);
    }

    /** @return array<array-key, mixed> The decoded JSON body */
    protected function json(ResponseInterface $response): array
    {
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    /** @return array<array-key, mixed> The "data" of the body: one record, or a list of them */
    protected function data(ResponseInterface $response): array
    {
        $data = $this->json($response)['data'] ?? null;
        self::assertIsArray($data);

        return $data;
    }
}
