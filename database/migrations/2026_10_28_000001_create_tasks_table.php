<?php

declare(strict_types=1);

/*
 * Built with the DBAL schema API rather than raw SQL, so the same migration
 * runs on SQLite, MySQL and PostgreSQL.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

return static function (Connection $connection): void {
    $connection->createSchemaManager()->createTable(
        Table::editor()
            ->setUnquotedName('tasks')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('uuid')
                    ->setTypeName(Types::STRING)
                    ->setLength(36)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('title')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('done')
                    ->setTypeName(Types::BOOLEAN)
                    ->setDefaultValue(false)
                    ->create(),
                // A list of subtask objects; a JSON column needs no second table
                Column::editor()
                    ->setUnquotedName('subtasks')
                    ->setTypeName(Types::JSON)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_IMMUTABLE)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('updated_at')
                    ->setTypeName(Types::DATETIME_IMMUTABLE)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                // The `unique` rule answers a duplicate with a 422; the index is
                // what holds when two requests pass the rule at once
                Index::editor()
                    ->setUnquotedName('tasks_title_unique')
                    ->setUnquotedColumnNames('title')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
                Index::editor()
                    ->setUnquotedName('tasks_uuid_unique')
                    ->setUnquotedColumnNames('uuid')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
                Index::editor()->setUnquotedName('tasks_done_index')->setUnquotedColumnNames('done')->create(),
                // The list filters and sorts by it
                Index::editor()
                    ->setUnquotedName('tasks_created_at_index')
                    ->setUnquotedColumnNames('created_at')
                    ->create(),
            )
            ->create(),
    );
};
