<?php

declare(strict_types=1);

namespace App\Modules\Task\Inputs;

use Solo\Input\Attributes\Exclude;
use Solo\Input\Attributes\Input;
use Solo\Input\Attributes\Items;
use Solo\Input\Attributes\Normalize;
use Solo\Input\Attributes\Source\FromRoute;
use Solo\Input\Attributes\Validate;

/**
 * PATCH /tasks/{id} {"done": true}
 *
 * Partial: Inputs::toArray() gives only the fields the client sent, so the
 * defaults never overwrite what is stored, and a list sent here replaces the
 * stored one as a whole.
 *
 * The id comes from the route — #[FromRoute], kept out of the change set by
 * #[Exclude] — and `unique:tasks,title,{id}` reads it, so a task's own title is
 * not a duplicate of itself.
 */
#[Input(partial: true)]
final readonly class TaskUpdateInput
{
    /** @param list<SubtaskInput>|null $subtasks */
    public function __construct(
        #[FromRoute]
        #[Exclude]
        #[Validate('required|integer|min_value:1')]
        public int $id,
        #[Validate('filled|string|max:255|unique:tasks,title,{id}')]
        public string $title = '',
        #[Validate('filled|boolean')]
        public bool $done = false,
        #[Validate('array|max:20')]
        #[Normalize(SubtaskListNormalizer::class)]
        #[Items(SubtaskInput::class)]
        public ?array $subtasks = null,
    ) {
    }
}
