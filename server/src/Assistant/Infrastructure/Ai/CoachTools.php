<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Application\TryWhatIf;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Seat;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The coach's tools, for one deal and one seat. Each is a capability of the game asked as it is and
 * returned as it is: nothing is computed here. Every result is also written to the facts of the
 * conversation, which is what the answer will be checked against. A question the model asked wrongly
 * (a ninth trick, a card the player did not hold) is answered in words, and adds no fact.
 */
#[AsTool('deal_summary', 'The deal in short: the auction, the contract, whether it was made, and what each team made (tricks, card points, score-sheet points).', 'dealSummary')]
#[AsTool('trick', 'One trick as it was played: the four cards in order, who led, who won, the card points in it, and the card the player put in it.', 'trick')]
#[AsTool('expert_choice', 'What the expert would have played at the player\'s turn in one trick, knowing only what the player could know then: the player\'s hand, the cards on the table, every legal card with its average result, and the loss of the card played against the expert\'s.', 'expertChoice')]
#[AsTool('what_if', 'How the deal ends if the player plays another card in one trick, next to how it ends from the card really played; both finished by the same four experts.', 'whatIf')]
#[AsTool('biggest_swings', 'The player\'s decisions the expert would have made otherwise, the costliest first, each with its trick, the card played, the expert\'s card and the loss on average. A decision that is not in this list is not a mistake.', 'biggestSwings')]
final readonly class CoachTools
{
    public function __construct(
        private DealRecord $record,
        private Seat $seat,
        private Facts $facts,
        private SummariseDeal $summarise,
        private ReviewTrick $reviewTrick,
        private AskExpertChoice $askExpertChoice,
        private TryWhatIf $tryWhatIf,
        private RankSwings $rankSwings,
    ) {
    }

    /** @return array<string, mixed> */
    public function dealSummary(): array
    {
        return $this->established(($this->summarise)($this->record, $this->seat)->toArray());
    }

    /**
     * @param int $n the trick, from 1 to 8
     *
     * @return array<string, mixed>|string
     */
    public function trick(int $n): array|string
    {
        $trick = TrickNumber::tryFrom($n);

        return null === $trick ? self::noSuchTrick($n) : $this->established(($this->reviewTrick)($this->record, $this->seat, $trick)->toArray());
    }

    /**
     * @param int $n the trick, from 1 to 8
     *
     * @return array<string, mixed>|string
     */
    public function expertChoice(int $n): array|string
    {
        $trick = TrickNumber::tryFrom($n);

        return null === $trick ? self::noSuchTrick($n) : $this->established(($this->askExpertChoice)($this->record, $this->seat, $trick)->toArray());
    }

    /**
     * @param int    $n    the trick, from 1 to 8
     * @param string $card the card to play instead, rank then suit as in the other results, for example QH
     *
     * @return array<string, mixed>|string
     */
    public function whatIf(int $n, string $card): array|string
    {
        $trick = TrickNumber::tryFrom($n);
        if (null === $trick) {
            return self::noSuchTrick($n);
        }
        $played = Card::tryFromCode(strtoupper(trim($card)));
        if (null === $played) {
            return 'No such card: a card is a rank (7 8 9 T J Q K A) then a suit (S H D C), for example QH.';
        }
        try {
            return $this->established(($this->tryWhatIf)($this->record, $this->seat, $trick, $played)->toArray());
        } catch (EngineRefused $refused) {
            // the record itself was accepted before the model was asked: what is refused here is the card
            return 'The engine refused: '.$refused->getMessage().'. Ask expert_choice for the cards the player could play in that trick.';
        }
    }

    /** @return array<string, mixed> */
    public function biggestSwings(): array
    {
        return $this->established(($this->rankSwings)($this->record, $this->seat)->toArray());
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function established(array $result): array
    {
        $this->facts->add($result);

        return $result;
    }

    private static function noSuchTrick(int $n): string
    {
        return \sprintf('No trick %d: a deal has tricks %d to %d.', $n, TrickNumber::FIRST, TrickNumber::LAST);
    }
}
