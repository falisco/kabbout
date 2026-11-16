<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** This visitor asks the coach faster than allowed. */
final class AskedTooOften extends \RuntimeException
{
    /** @param int $retryAfter seconds until the next question may be asked */
    public function __construct(public int $retryAfter)
    {
        parent::__construct('This visitor asks the coach faster than allowed.');
    }
}
