<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Exceptions\NotFoundException;
use InvalidArgumentException;
use Solo\Repository\ReadQuery;
use Solo\Repository\Repository;

/**
 * A row read by id: the row, or a 404 when the table has none. The 404 of a
 * missing record is one decision, so it is made here instead of in a private
 * find() of every controller. A read keeps its own visibility: a row it cannot
 * see is missing too.
 */
final class Records
{
    /**
     * @template T of object
     * @param Repository<T>|ReadQuery<T> $source The table, or a read with relations or a visibility of its own
     * @return T
     * @throws NotFoundException
     */
    public static function findOrFail(Repository|ReadQuery $source, int|string $id): object
    {
        $read = $source instanceof Repository ? $source->read() : $source;

        try {
            $row = $read->find($id);
        } catch (InvalidArgumentException) {
            // An id no primary key can take — "007", a number past the PHP
            // integer range — names no row either
            $row = null;
        }

        return $row ?? throw new NotFoundException();
    }
}
