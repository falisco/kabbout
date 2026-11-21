<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application;

/** The questions of the day, all visitors together, are spent. */
final class DaySpent extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The questions of the day, all visitors together, are spent.');
    }
}
