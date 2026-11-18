<?php

declare(strict_types=1);

namespace Kabbout\Game\Application;

use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;

/**
 * One trick of a recorded deal as it was played.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class ReviewTrick
{
    /** what this tells, in the words every door that offers it to a model uses */
    public const string ABOUT = 'One trick as it was played: the four cards in order, who led, who won, the card points in it, and the card the player put in it.';

    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat, TrickNumber $trick): TrickReview
    {
        return $this->analysis->trick($record, $seat, $trick);
    }
}
