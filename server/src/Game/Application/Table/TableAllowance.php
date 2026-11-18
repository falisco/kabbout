<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Identity\Domain\NetworkAddress;

/**
 * How fast one place may ask the tables for something. Every call is work for the engine, the expert
 * robot's most of all, and a guest costs nothing to make: the count is per address, like the others.
 */
interface TableAllowance
{
    /** @throws PlayedTooFast */
    public function pace(NetworkAddress $from): void;
}
