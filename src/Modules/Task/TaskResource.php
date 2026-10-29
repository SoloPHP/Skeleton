<?php

declare(strict_types=1);

namespace App\Modules\Task;

use App\Modules\Task\Models\Subtask;
use App\Modules\Task\Models\Task;
use DateTimeInterface;

/**
 * How a task looks in a response. The model stays free of presentation, and
 * the JSON keeps its shape when the table changes.
 *
 * @phpstan-type TaskArray array{
 *     id: int,
 *     uuid: string,
 *     title: string,
 *     done: bool,
 *     subtasks: list<array{title: string, done: bool}>,
 *     created_at: string,
 *     updated_at: string,
 * }
 */
final class TaskResource
{
    /** @return TaskArray */
    public static function make(Task $task): array
    {
        return [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'done' => $task->done,
            'subtasks' => array_map(static fn(Subtask $subtask): array => $subtask->toArray(), $task->subtasks),
            'created_at' => $task->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $task->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<Task> $tasks
     * @return list<TaskArray>
     */
    public static function collection(array $tasks): array
    {
        return array_map(self::make(...), $tasks);
    }
}
