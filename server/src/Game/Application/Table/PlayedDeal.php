<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Seat;

/** A deal played to its end at a table, and the seat its player sat at: what a review is asked about. */
final readonly class PlayedDeal
{
    public function __construct(public DealRecord $record, public Seat $seat)
    {
    }
}
