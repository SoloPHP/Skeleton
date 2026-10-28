<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeInterface;

/**
 * How a task looks in a response. The model stays free of presentation, and
 * the JSON keeps its shape when the table changes.
 */
final class TaskResource
{
    /** @return array{id: int, title: string, done: bool, created_at: string, updated_at: string} */
    public static function make(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'done' => $task->done,
            'created_at' => $task->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $task->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<Task> $tasks
     * @return list<array{id: int, title: string, done: bool, created_at: string, updated_at: string}>
     */
    public static function collection(array $tasks): array
    {
        return array_map(self::make(...), $tasks);
    }
}
