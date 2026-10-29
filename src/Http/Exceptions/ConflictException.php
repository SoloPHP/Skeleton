<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

/**
 * A write a validation rule passed cannot be stored anyway: another request took
 * the unique value between the check and the insert. The client's retry, or its
 * next read, shows what it collided with.
 */
final class ConflictException extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'CONFLICT');
    }
}
