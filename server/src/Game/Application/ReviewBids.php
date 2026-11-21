<?php

declare(strict_types=1);

namespace Kabbout\Game\Application;

use Kabbout\Game\Domain\Analysis\BidReview;
use Kabbout\Game\Domain\ConventionCard;
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
    /** what this tells, in the words every door that offers it to a model uses */
    public const string ABOUT = 'The auction at each of the player\'s turns to speak: the bids heard so far, what the player said, what the expert would have said with the same cards and why, and what the player\'s hand is worth with each suit as trumps. The expert bids by conventions, the classic ones or those the player agreed on with their partner: this says what it would have done, not what a bid cost.';

    public function __construct(private DealAnalysis $analysis)
    {
    }

    /**
     * @param ConventionCard|null $card the conventions the player and their partner bid by in that deal; none, the classic ones
     *
     * @throws EngineRefused for a record the engine cannot play again
     */
    public function __invoke(DealRecord $record, Seat $seat, Lang $lang, ?ConventionCard $card = null): BidReview
    {
        return $this->analysis->bids($record, $seat, $lang, $card);
    }
}
