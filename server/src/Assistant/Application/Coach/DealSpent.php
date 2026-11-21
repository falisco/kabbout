<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** The visitor has no question left about this deal. */
final class DealSpent extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The visitor has no question left about this deal.');
    }
}
