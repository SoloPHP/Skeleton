<?php

declare(strict_types=1);

namespace App\Modules\Task\Inputs;

use App\Input\Generators\UuidGenerator;
use Solo\Input\Attributes\Generator;
use Solo\Input\Attributes\Input;
use Solo\Input\Attributes\Items;
use Solo\Input\Attributes\Normalize;
use Solo\Input\Attributes\Validate;

/**
 * POST /tasks {"title": "Write the docs", "subtasks": [{"title": "Write the code"}]}
 *
 * A field without a default is required; one with a default may be left out.
 * `unique` is the application's own rule (src/Validation/UniqueRule.php), and
 * `uuid` is generated: a value the client sends for it is ignored.
 *
 * `subtasks` also takes plain strings — ["Write the code"] — which the
 * normalizer turns into the objects #[Items] builds.
 */
#[Input]
final readonly class TaskStoreInput
{
    /** @param list<SubtaskInput> $subtasks */
    public function __construct(
        #[Generator(UuidGenerator::class)]
        public string $uuid,
        #[Validate('required|string|max:255|unique:tasks,title')]
        public string $title,
        #[Validate('boolean')]
        public bool $done = false,
        #[Validate('array|max:20')]
        #[Normalize(SubtaskListNormalizer::class)]
        #[Items(SubtaskInput::class)]
        public array $subtasks = [],
    ) {
    }
}
