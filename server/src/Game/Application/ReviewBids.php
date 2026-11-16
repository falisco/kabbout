<?php

declare(strict_types=1);

namespace Kabbout\Game\Application;

use Kabbout\Game\Domain\Analysis\BidReview;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;

/**
 * The seat's bids next to the expert robot's, turn by turn, with the expert's reasons in one language.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class ReviewBids
{
    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat, Lang $lang): BidReview
    {
        return $this->analysis->bids($record, $seat, $lang);
    }
}
