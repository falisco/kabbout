<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

/** New guests come in from one address faster than allowed. */
final class EnteredTooOften extends \RuntimeException
{
    /** @param int $retryAfter seconds until the next guest may come in from there */
    public function __construct(public int $retryAfter)
    {
        parent::__construct('New guests come in from one address faster than allowed.');
    }
}
