<?php

declare(strict_types=1);

namespace Kabbout\Game\Application;

use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;

/**
 * The deal in a few lines: the auction, the contract, what each team made.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class SummariseDeal
{
    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat): DealSummary
    {
        return $this->analysis->summary($record, $seat);
    }
}
