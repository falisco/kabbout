<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

/** The tables are asked from one address faster than allowed. */
final class PlayedTooFast extends \RuntimeException implements TableRefusal
{
    /** @param int $retryAfter seconds until the next call is taken */
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(\sprintf('Too many calls from one place. Try again in %d seconds.', $retryAfter));
    }
}
