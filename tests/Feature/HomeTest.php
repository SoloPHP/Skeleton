<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HomeTest extends TestCase
{
    public function testHomeAnswers(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $this->data($response)['status'] ?? null);
    }

    public function testUnknownPathIsJson404(): void
    {
        $response = $this->request('GET', '/nowhere');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'NOT_FOUND'], $this->json($response));
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $response = $this->request('PUT', '/tasks');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['error' => 'METHOD_NOT_ALLOWED'], $this->json($response));
        self::assertStringContainsString('POST', $response->getHeaderLine('Allow'));
    }

    public function testMalformedJsonIs400(): void
    {
        $response = $this->request('POST', '/tasks', '{"title": ');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'INVALID_JSON'], $this->json($response));
    }
}
