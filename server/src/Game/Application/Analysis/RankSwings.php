<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Analysis;

use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;

/**
 * The seat's decisions the expert would have made otherwise, the costliest first.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class RankSwings
{
    /** what this tells, in the words every door that offers it to a model uses */
    public const string ABOUT = 'The player\'s decisions the expert would have made otherwise, the costliest first, each with its trick, the card played, the expert\'s card and the loss on average. A decision that is not in this list is not a mistake.';

    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat): Swings
    {
        return $this->analysis->swings($record, $seat);
    }
}
