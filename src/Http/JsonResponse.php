<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Solo\Repository\Page;

/**
 * JSON bodies in the one shape every endpoint answers with:
 *
 *     {"data": ...}                                  a record or a list
 *     {"data": [...], "meta": {"pagination": ...}}   one page of a list
 *     {"error": "NOT_FOUND"}                         an error, with more keys when it has details
 *
 * It writes into the response a handler was given, so the application never
 * depends on a particular PSR-7 implementation.
 */
final class JsonResponse
{
    private const int FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @param array<string, mixed> $meta */
    public static function data(
        ResponseInterface $response,
        mixed $data,
        int $status = 200,
        array $meta = [],
    ): ResponseInterface {
        return self::write($response, $meta === [] ? ['data' => $data] : ['data' => $data, 'meta' => $meta], $status);
    }

    /** @param Page<mixed> $page */
    public static function page(ResponseInterface $response, Page $page): ResponseInterface
    {
        $perPage = $page->request->perPage;

        return self::data($response, $page->items, meta: [
            'pagination' => [
                'page' => $page->request->page,
                'per_page' => $perPage,
                'total' => $page->total,
                'pages' => max(1, (int) ceil($page->total / $perPage)),
            ],
        ]);
    }

    /** @param array<string, mixed> $details Extra keys beside the code, such as the fields that failed */
    public static function error(
        ResponseInterface $response,
        string $code,
        int $status,
        array $details = [],
    ): ResponseInterface {
        return self::write($response, ['error' => $code] + $details, $status);
    }

    /** @param array<string, mixed> $payload */
    private static function write(ResponseInterface $response, array $payload, int $status): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, self::FLAGS));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
