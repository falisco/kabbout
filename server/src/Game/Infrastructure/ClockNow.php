<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure;

use Kabbout\Game\Application\Table\Now;
use Psr\Clock\ClockInterface;

/** The time of the tables is the server's clock. */
final readonly class ClockNow implements Now
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function seconds(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
