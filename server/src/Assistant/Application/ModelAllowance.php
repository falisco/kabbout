<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application;

use Kabbout\Identity\Domain\NetworkAddress;

/**
 * How much the model may be asked, whatever it is asked for. Every call is paid for, so two counts stand
 * before each: a visitor's pace, and what all visitors together may ask in a day. They are one budget,
 * shared by every feature that calls the model: there is one key and one bill.
 */
interface ModelAllowance
{
    /** @throws AskedTooOften when this visitor asks faster than allowed */
    public function pace(NetworkAddress $from): void;

    /** @throws DaySpent when the day's questions, all visitors together, are spent */
    public function day(): void;
}
