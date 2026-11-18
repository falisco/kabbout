<?php

declare(strict_types=1);

namespace Kabbout\Game\Application;

use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;

/**
 * How a recorded deal ends if the seat plays another card in one trick.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class TryWhatIf
{
    /** what this tells, in the words every door that offers it to a model uses */
    public const string ABOUT = 'How the deal ends if the player plays another card in one trick, next to how it ends from the card really played; both finished by the same four experts.';

    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat, TrickNumber $trick, Card $card): WhatIf
    {
        return $this->analysis->whatIf($record, $seat, $trick, $card);
    }
}
