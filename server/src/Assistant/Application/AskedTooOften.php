<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application;

/** This visitor asks the model faster than allowed, whatever they ask it for. */
final class AskedTooOften extends \RuntimeException
{
    /** @param int $retryAfter seconds until the next question may be asked */
    public function __construct(public int $retryAfter)
    {
        parent::__construct('This visitor asks faster than allowed.');
    }
}
