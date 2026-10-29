<?php

declare(strict_types=1);

namespace App\Input\PostProcessors;

use DateTimeImmutable;
use LogicException;
use Solo\Input\Contracts\PostProcessorInterface;
use Solo\Input\ProcessContext;

/**
 * A range of calendar days as `>=` / `<=` conditions on a DATETIME column:
 *
 *     ?created[from]=2026-10-01&created[to]=2026-10-29
 *     → ['created_at >=' => 2026-10-01 00:00:00, 'created_at <=' => 2026-10-29 23:59:59]
 *
 * The column comes from `config: ['field' => 'created_at']`. A bound that is not
 * a day the client could have meant is dropped rather than rejected: a filter
 * that cannot be applied behaves as one that was never sent.
 *
 * @implements PostProcessorInterface<mixed>
 */
final class DateRangePostProcessor implements PostProcessorInterface
{
    private const string DAY = '!Y-m-d';

    /** @return array<string, DateTimeImmutable> */
    public function postProcess(mixed $value, ProcessContext $context): array
    {
        $field = $context->config['field'] ?? null;
        if (!is_string($field) || $field === '') {
            throw new LogicException(self::class . " needs config: ['field' => '<column>'].");
        }

        if (!is_array($value)) {
            return [];
        }

        $conditions = [];

        $from = self::day($value['from'] ?? null, endOfDay: false);
        if ($from !== null) {
            $conditions[$field . ' >='] = $from;
        }

        $to = self::day($value['to'] ?? null, endOfDay: true);
        if ($to !== null) {
            $conditions[$field . ' <='] = $to;
        }

        return $conditions;
    }

    /**
     * Null when that is not a day: `2026-02-31` comes back as `2026-03-03`.
     */
    private static function day(mixed $raw, bool $endOfDay): ?DateTimeImmutable
    {
        if (!is_string($raw)) {
            return null;
        }

        $text = trim($raw);
        $day = DateTimeImmutable::createFromFormat(self::DAY, $text);
        if ($day === false || $day->format('Y-m-d') !== $text) {
            return null;
        }

        return $endOfDay ? $day->setTime(23, 59, 59) : $day;
    }
}
