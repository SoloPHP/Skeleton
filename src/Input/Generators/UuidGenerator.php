<?php

declare(strict_types=1);

namespace App\Input\Generators;

use Solo\Input\Contracts\GeneratorInterface;

/**
 * A version 4 UUID for a field the client never sends:
 *
 *     #[Generator(UuidGenerator::class)]
 *     public string $uuid,
 *
 * solophp/input has no generators of its own, and the container builds this one,
 * so it may take dependencies as any other service.
 */
final class UuidGenerator implements GeneratorInterface
{
    /** @param array<string, mixed> $options */
    public function generate(array $options = []): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // variant 1

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
