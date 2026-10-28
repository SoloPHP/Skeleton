<?php

declare(strict_types=1);

namespace App\Task\Inputs;

use App\Http\Casters\SortCaster;
use Solo\Input\Attributes\Caster;
use Solo\Input\Attributes\EmptyToNull;
use Solo\Input\Attributes\Input;
use Solo\Input\Attributes\Validate;
use Solo\Repository\Like;

/**
 * GET /tasks?q=docs&done=false&sort=-created_at&page=2&per_page=10
 *
 * Each filter defaults to null, and a filter the client did not send narrows
 * nothing. A filter sent blank, as in `?q=` or `?done=`, has no value either:
 * #[EmptyToNull] reads a blank string as null, and a blank bool is null anyway.
 */
#[Input]
final readonly class TaskIndexInput
{
    /** @param array<string, 'ASC'|'DESC'> $sort */
    public function __construct(
        #[Validate('string|max:100')]
        #[EmptyToNull]
        public ?string $q = null,
        #[Validate('boolean')]
        public ?bool $done = null,
        #[Validate('integer|min_value:1')]
        public int $page = 1,
        #[Validate('integer|min_value:1|max_value:100')]
        public int $per_page = 20,
        #[Validate('in:id,-id,title,-title,created_at,-created_at')]
        #[Caster(SortCaster::class)]
        public array $sort = ['id' => 'DESC'],
    ) {
    }

    /**
     * The filters as repository criteria. Like::contains() escapes the search
     * text, so a "%" or "_" the client types matches only itself.
     *
     * @return array<string, mixed>
     */
    public function criteria(): array
    {
        return array_filter([
            'title LIKE' => $this->q === null ? null : Like::contains($this->q),
            'done' => $this->done,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
