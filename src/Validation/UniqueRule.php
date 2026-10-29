<?php

declare(strict_types=1);

namespace App\Validation;

use Doctrine\DBAL\Connection;
use Solo\Validator\Field;
use Solo\Validator\InvalidRuleException;
use Solo\Validator\ParameterizedRule;
use Solo\Validator\RuleInterface;

/**
 * `unique:tasks,title` — no other row holds this value in that column;
 * `unique:tasks,title,{id}` — no other row but the payload's `id`, so a PATCH
 * that keeps its title passes. `{...}` is read from the validation payload, so
 * the rule need not know the name a route gives the row. A fourth parameter
 * names the key that row is found by, for a table whose key is not `id`:
 * `unique:users,email,{uuid},uuid`.
 *
 * It needs the database, so InputServiceProvider builds it once for every input.
 */
final readonly class UniqueRule implements RuleInterface, ParameterizedRule
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Checked when the rule string is compiled, before any input arrives.
     *
     * @throws InvalidRuleException A rule string such as `unique:tasks`
     */
    public function checkParams(array $params): void
    {
        if (count($params) < 2 || count($params) > 4) {
            throw InvalidRuleException::invalidParams(
                'a table, a column and, optionally, the id of the changed row and the key it is compared by',
            );
        }
    }

    /** @return array{rule: string}|null */
    public function validate(mixed $value, array $params, Field $field): ?array
    {
        // A value that is not there is the business of `required` and `filled`
        if ($value === null || $value === '' || self::namesAnAbsentField($params, $field->data)) {
            return null;
        }

        // One part of a name at a time: the platform quotes each part, and a
        // dotted name would be the caller's to split. Connection::quoteIdentifier()
        // is deprecated for guessing at that split.
        $query = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($this->connection->quoteSingleIdentifier($params[0]))
            ->where($this->connection->quoteSingleIdentifier($params[1]) . ' = :value')
            ->setParameter('value', $value)
            ->setMaxResults(1);

        $id = self::changedId($params[2] ?? null, $field->data);
        if ($id !== null) {
            $key = $this->connection->quoteSingleIdentifier($params[3] ?? 'id');
            $query->andWhere($key . ' <> :id')->setParameter('id', $id);
        }

        // The error code is the whole answer: table and column names are the
        // schema's business, not the client's.
        return $query->executeQuery()->fetchOne() === false ? null : ['rule' => 'unique'];
    }

    /**
     * Whether a `{field}` reference names a field the payload does not carry. A
     * field the client left out is absent from it, and the absence is that
     * field's own rules' to report; a guessed value would only add a second,
     * misleading error.
     *
     * @param list<string> $params
     * @param array<string, mixed> $data
     */
    private static function namesAnAbsentField(array $params, array $data): bool
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', implode(',', $params), $names);

        return array_diff($names[1], array_keys($data)) !== [];
    }

    /**
     * The id in the payload: the row being changed, never a duplicate of itself.
     *
     * @param array<string, mixed> $data
     */
    private static function changedId(?string $reference, array $data): int|string|null
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (preg_match('/\A\{([a-zA-Z_][a-zA-Z0-9_]*)\}\z/', $reference, $matches) !== 1) {
            return $reference;
        }

        $value = $data[$matches[1]] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }
}
