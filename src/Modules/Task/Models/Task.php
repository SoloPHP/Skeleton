<?php

declare(strict_types=1);

namespace App\Modules\Task\Models;

use DateTimeImmutable;

/**
 * The model: a row of `tasks`, as TaskRepository hydrates it and TaskResource
 * turns it into an array.
 */
final readonly class Task
{
    /** @param list<Subtask> $subtasks */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $title,
        public bool $done,
        public array $subtasks,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
