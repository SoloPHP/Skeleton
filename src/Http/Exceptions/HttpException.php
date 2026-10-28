<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use RuntimeException;

/**
 * An error the client caused, answered with its status and code. Throw it from
 * anywhere below ErrorMiddleware instead of building an error response by hand.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
    ) {
        parent::__construct($errorCode, $status);
    }
}
