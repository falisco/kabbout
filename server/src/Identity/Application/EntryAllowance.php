<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;

/**
 * How many new guests may come in from one place. A guest costs nothing to whoever asks for one and is
 * kept for a month: without a count, a script could fill the disk, or take every name there is.
 */
interface EntryAllowance
{
    /**
     * @throws EnteredTooOften    when guests come in from this address faster than allowed
     * @throws PlayersUnavailable when the count cannot be kept
     */
    public function pace(NetworkAddress $from): void;
}
