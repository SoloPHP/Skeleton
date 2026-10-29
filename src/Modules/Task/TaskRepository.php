<?php

declare(strict_types=1);

namespace App\Modules\Task;

use App\Http\Exceptions\ConflictException;
use App\Modules\Task\Models\Subtask;
use App\Modules\Task\Models\Task;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use LogicException;
use Solo\Repository\Mapping;
use Solo\Repository\Repository;

/**
 * The `tasks` table. Repository gives the reads and writes; the subclass fixes
 * the mapping and adds what every write needs: its timestamps, and a subtask
 * list the column can read back.
 *
 * @extends Repository<Task>
 */
final readonly class TaskRepository extends Repository
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, self::mapping());
    }

    /** @return Mapping<Task> */
    public static function mapping(): Mapping
    {
        return new Mapping(
            table: 'tasks',
            columns: [
                'id' => Types::INTEGER,
                'uuid' => Types::STRING,
                'title' => Types::STRING,
                'done' => Types::BOOLEAN,
                'subtasks' => Types::JSON,
                'created_at' => Types::DATETIME_IMMUTABLE,
                'updated_at' => Types::DATETIME_IMMUTABLE,
            ],
            hydrate: self::hydrate(...),
        );
    }

    /** @param array<string, mixed> $row A row the column types above have already converted to PHP values */
    private static function hydrate(array $row): Task
    {
        /**
         * @var array{
         *     id: int,
         *     uuid: string,
         *     title: string,
         *     done: bool,
         *     subtasks: list<array{title: string, done: bool}>,
         *     created_at: DateTimeImmutable,
         *     updated_at: DateTimeImmutable,
         * } $row
         */
        return new Task(
            id: $row['id'],
            uuid: $row['uuid'],
            title: $row['title'],
            done: $row['done'],
            subtasks: array_map(Subtask::fromRow(...), $row['subtasks']),
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    /** @param array<string, mixed> $fields */
    public function create(array $fields): Task
    {
        $now = new DateTimeImmutable();
        // `subtasks` is NOT NULL, and a caller that has no list to write sends none
        $fields = $this->withSubtaskDefaults($fields + ['subtasks' => []])
            + ['created_at' => $now, 'updated_at' => $now];

        try {
            $id = $this->insertGetId($fields);
        } catch (UniqueConstraintViolationException) {
            // The `unique` rule passed, so another row took the value in between
            throw new ConflictException();
        }

        return $this->read()->find($id) ?? throw new LogicException("Task $id is missing right after its insert");
    }

    /** @param array<string, mixed> $changes Only the fields to change */
    public function change(Task $task, array $changes): Task
    {
        if ($changes === []) {
            return $task;
        }

        try {
            $this->update($task->id, $this->withSubtaskDefaults($changes) + ['updated_at' => new DateTimeImmutable()]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException();
        }

        return $this->read()->find($task->id) ?? throw new LogicException("Task $task->id is missing after its update");
    }

    /**
     * Fills in the subtask defaults a partial input leaves out, so that what is
     * written is what hydrate() reads.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function withSubtaskDefaults(array $fields): array
    {
        if (!array_key_exists('subtasks', $fields)) {
            return $fields;
        }

        $subtasks = $fields['subtasks'];

        // A partial input exports a list the client did not send as null, and
        // an empty list is what no list means to the column
        if ($subtasks === null) {
            $fields['subtasks'] = [];

            return $fields;
        }

        if (is_array($subtasks)) {
            /** @var list<array{title: string, done?: bool}> $subtasks */
            $fields['subtasks'] = Subtask::rows($subtasks);
        }

        return $fields;
    }
}
