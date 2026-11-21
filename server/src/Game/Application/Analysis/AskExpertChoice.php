<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Analysis;

use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;

/**
 * What the expert robot would have played at the seat's turn in one trick, from what the seat could know.
 * One of the capabilities every way in shares: the coach's tools today, other clients tomorrow.
 */
final readonly class AskExpertChoice
{
    /** what this tells, in the words every door that offers it to a model uses */
    public const string ABOUT = 'What the expert would have played at the player\'s turn in one trick, knowing only what the player could know then: the player\'s hand, the cards on the table, every legal card with its average result, the loss of the card played against the expert\'s, and the table reading that explains it: cards fallen, cards and trumps still out, master cards, seats void in a suit, who held the trick, the points made so far.';

    public function __construct(private DealAnalysis $analysis)
    {
    }

    /** @throws EngineRefused for a record the engine cannot play again */
    public function __invoke(DealRecord $record, Seat $seat, TrickNumber $trick): ExpertChoice
    {
        return $this->analysis->expertChoice($record, $seat, $trick);
    }
}
