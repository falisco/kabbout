<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

use Kabbout\Game\Domain\Analysis\BidReview;
use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;

/**
 * A recorded deal looked at again, for the seat that asks. Every number comes from the engine, which
 * plays the record first: a deal the rules do not allow is refused, with the place where it goes wrong.
 * The same question about the same deal always gets the same answer.
 *
 * @throws EngineRefused     on every method, for a record the engine cannot play again
 * @throws EngineUnavailable when the engine cannot be asked
 */
interface DealAnalysis
{
    public function summary(DealRecord $record, Seat $seat): DealSummary;

    public function trick(DealRecord $record, Seat $seat, TrickNumber $trick): TrickReview;

    /** What the expert would have played at the seat's turn, from what the seat could know then. */
    public function expertChoice(DealRecord $record, Seat $seat, TrickNumber $trick): ExpertChoice;

    /**
     * How the deal ends if the seat plays `card` in that trick.
     *
     * @throws EngineRefused also for a card the seat could not play there
     */
    public function whatIf(DealRecord $record, Seat $seat, TrickNumber $trick, Card $card): WhatIf;

    public function swings(DealRecord $record, Seat $seat): Swings;

    /** The seat's bids next to the expert's, from what the seat could know at each turn; the reasons in `$lang`. */
    public function bids(DealRecord $record, Seat $seat, Lang $lang, ?ConventionCard $card = null): BidReview;
}
