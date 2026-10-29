<?php

declare(strict_types=1);

namespace App\Modules\Task\Inputs;

use Solo\Input\Attributes\Input;
use Solo\Input\Attributes\Validate;

/**
 * One element of POST /tasks {"subtasks": [{"title": "Write the code"}]}
 *
 * It is an input class of its own, so #[Items] builds a list of them, and an
 * error comes back under the path the client used: subtasks.0.title.
 */
#[Input]
final readonly class SubtaskInput
{
    public function __construct(
        #[Validate('required|string|max:255')]
        public string $title,
        #[Validate('boolean')]
        public bool $done = false,
    ) {
    }
}
