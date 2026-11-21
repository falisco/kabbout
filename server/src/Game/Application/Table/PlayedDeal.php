<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Seat;

/**
 * A deal played to its end at a table, the seat its player sat at, and the conventions the player and
 * their partner bid by (none: the classic ones): what a review is asked about.
 */
final readonly class PlayedDeal
{
    public function __construct(public DealRecord $record, public Seat $seat, public ?ConventionCard $card = null)
    {
    }
}
