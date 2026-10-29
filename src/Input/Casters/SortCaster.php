<?php

declare(strict_types=1);

namespace App\Input\Casters;

use Solo\Input\Contracts\CasterInterface;
use Solo\Input\ProcessContext;

/**
 * `?sort=title` and `?sort=-created_at` as the order a repository read takes:
 * ['title' => 'ASC'], ['created_at' => 'DESC']. The field's `in:` rule decides
 * which columns a client may sort by.
 *
 * @implements CasterInterface<array<string, 'ASC'|'DESC'>>
 */
final class SortCaster implements CasterInterface
{
    /** @return array<string, 'ASC'|'DESC'> */
    public function cast(mixed $value, ProcessContext $context): array
    {
        if (!is_string($value) || $value === '' || $value === '-') {
            return [];
        }

        return str_starts_with($value, '-') ? [substr($value, 1) => 'DESC'] : [$value => 'ASC'];
    }
}
