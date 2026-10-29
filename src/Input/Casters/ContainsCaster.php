<?php

declare(strict_types=1);

namespace App\Input\Casters;

use LogicException;
use Solo\Input\Contracts\CasterInterface;
use Solo\Input\ProcessContext;
use Solo\Repository\Like;

/**
 * The text of a search box as the LIKE pattern a repository read takes:
 * `?q=solo` → `%solo%`.
 *
 * A caster rather than a rule or a controller line, because what the criteria
 * need is not the text the client typed. Like::contains() escapes `%`, `_` and
 * `\`, so a `%` in the box matches only itself instead of widening the search.
 *
 * @implements CasterInterface<string>
 */
final class ContainsCaster implements CasterInterface
{
    public function cast(mixed $value, ProcessContext $context): string
    {
        // #[EmptyToNull] gives a blank search box no text at all, and no text
        // narrows nothing
        if ($value === null) {
            return '';
        }

        // The field's rules allow a string only, so anything else is a broken
        // contract: failing here beats searching for everything
        if (!is_string($value)) {
            throw new LogicException(sprintf('A search text is a string, %s given.', get_debug_type($value)));
        }

        return Like::contains($value);
    }
}
