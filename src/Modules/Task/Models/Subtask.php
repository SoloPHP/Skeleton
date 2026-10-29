<?php

declare(strict_types=1);

namespace App\Modules\Task\Models;

/**
 * A subtask, the value object a task's JSON column holds: fromRow() reads one
 * out, toArray() writes it back, rows() normalizes a whole list.
 */
final readonly class Subtask
{
    public function __construct(
        public string $title,
        public bool $done = false,
    ) {
    }

    /**
     * `done` may be missing from a stored document; false is what it means.
     *
     * @param array{title: string, done?: bool} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(title: $row['title'], done: $row['done'] ?? false);
    }

    /**
     * The whole list as the column keeps it, every element with both keys.
     *
     * @param list<array{title: string, done?: bool}> $rows
     * @return list<array{title: string, done: bool}>
     */
    public static function rows(array $rows): array
    {
        return array_map(static fn(array $row): array => self::fromRow($row)->toArray(), $rows);
    }

    /** @return array{title: string, done: bool} */
    public function toArray(): array
    {
        return ['title' => $this->title, 'done' => $this->done];
    }
}
