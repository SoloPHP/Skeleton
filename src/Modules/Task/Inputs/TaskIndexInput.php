<?php

declare(strict_types=1);

namespace App\Modules\Task\Inputs;

use App\Input\Casters\ContainsCaster;
use App\Input\Casters\SortCaster;
use App\Input\PostProcessors\DateRangePostProcessor;
use Solo\Input\Attributes\Caster;
use Solo\Input\Attributes\EmptyToNull;
use Solo\Input\Attributes\Group;
use Solo\Input\Attributes\Input;
use Solo\Input\Attributes\PostProcess;
use Solo\Input\Attributes\Validate;

/**
 * GET /tasks?q=docs&done=false&created[from]=2026-10-01&sort=-created_at&page=2
 *
 * Each filter defaults to null, and a filter the client did not send narrows
 * nothing. A filter sent blank, as in `?q=` or `?done=`, has no value either:
 * #[EmptyToNull] reads a blank string as null, and a blank bool is null anyway.
 *
 * #[Group('criteria')] makes the fields the criteria of a repository read:
 * Inputs::group() gives them as the keys `findBy()` takes, and a field left
 * null is not in the group, so it narrows nothing.
 */
#[Input]
final readonly class TaskIndexInput
{
    /**
     * @param array<string, mixed>|null $created `created[from]` / `created[to]` in, conditions on
     *        created_at out — see DateRangePostProcessor
     * @param array<string, 'ASC'|'DESC'> $sort
     */
    public function __construct(
        #[Validate('string|max:100')]
        #[EmptyToNull]
        #[Caster(ContainsCaster::class)]
        #[Group('criteria', mapTo: 'title LIKE')]
        public ?string $q = null,
        #[Validate('boolean')]
        #[Group('criteria')]
        public ?bool $done = null,
        #[Validate('array:from,to')]
        #[PostProcess(DateRangePostProcessor::class, config: ['field' => 'created_at'])]
        #[Group('criteria')]
        public ?array $created = null,
        #[Validate('integer|min_value:1')]
        public int $page = 1,
        #[Validate('integer|min_value:1|max_value:100')]
        public int $per_page = 20,
        #[Validate('in:id,-id,title,-title,created_at,-created_at')]
        #[Caster(SortCaster::class)]
        public array $sort = ['id' => 'DESC'],
    ) {
    }
}
