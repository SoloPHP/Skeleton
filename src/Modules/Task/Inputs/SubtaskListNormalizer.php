<?php

declare(strict_types=1);

namespace App\Modules\Task\Inputs;

use Solo\Input\Contracts\NormalizerInterface;
use Solo\Input\ProcessContext;

/**
 * A short way to write a list of subtasks: a plain string is the title of one.
 *
 *     {"subtasks": ["Write the code", {"title": "Write the docs", "done": true}]}
 *
 * It runs before validation, so #[Items] sees objects and an error still comes
 * back under `subtasks.0.title`. Anything else is passed on as it came.
 */
final class SubtaskListNormalizer implements NormalizerInterface
{
    public function normalize(mixed $value, ProcessContext $context): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = is_string($item) ? ['title' => $item] : $item;
        }

        return $normalized;
    }
}
