<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Domain\Grounding\CardVocabulary;
use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\ReviewBids;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Application\TryWhatIf;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The coach's tools, registered with the agent in config/packages/ai.yaml. Each is a capability of the
 * game asked as it is and returned as it is: nothing is computed here. The deal is the one of the
 * conversation under way, and every result is written to its facts, which is what the answer will be
 * checked against. A question the model asked wrongly (a ninth trick, a card the player did not hold)
 * is answered in words, and adds no fact.
 */
#[AsTool('deal_summary', SummariseDeal::ABOUT, 'dealSummary')]
#[AsTool('trick', ReviewTrick::ABOUT, 'trick')]
#[AsTool('expert_choice', AskExpertChoice::ABOUT, 'expertChoice')]
#[AsTool('what_if', TryWhatIf::ABOUT, 'whatIf')]
#[AsTool('expert_bids', ReviewBids::ABOUT, 'expertBids')]
#[AsTool('biggest_swings', RankSwings::ABOUT, 'biggestSwings')]
final readonly class CoachTools
{
    /** what a tool answers once the question has had its looks or its time: it adds no fact */
    private const string NO_MORE = 'No more looks at the deal for this question: answer now, with what the tools already returned.';

    public function __construct(
        private CoachConversation $conversation,
        private SummariseDeal $summarise,
        private ReviewTrick $reviewTrick,
        private AskExpertChoice $askExpertChoice,
        private TryWhatIf $tryWhatIf,
        private RankSwings $rankSwings,
        private ReviewBids $reviewBids,
    ) {
    }

    public function dealSummary(): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }

        return $this->established(($this->summarise)($this->conversation->record(), $this->conversation->seat())->toArray());
    }

    /**
     * @param int $n the trick, from 1 to 8
     */
    public function trick(int $n): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }
        $trick = TrickNumber::tryFrom($n);

        return null === $trick ? self::noSuchTrick($n) : $this->established(($this->reviewTrick)($this->conversation->record(), $this->conversation->seat(), $trick)->toArray());
    }

    /**
     * @param int $n the trick, from 1 to 8
     */
    public function expertChoice(int $n): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }
        $trick = TrickNumber::tryFrom($n);

        return null === $trick ? self::noSuchTrick($n) : $this->established(($this->askExpertChoice)($this->conversation->record(), $this->conversation->seat(), $trick)->toArray());
    }

    /**
     * @param int    $n    the trick, from 1 to 8
     * @param string $card the card to play instead, rank then suit as in the other results, for example QH
     */
    public function whatIf(int $n, string $card): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }
        $trick = TrickNumber::tryFrom($n);
        if (null === $trick) {
            return self::noSuchTrick($n);
        }
        $played = Card::tryFromCode(strtoupper(trim($card)));
        if (null === $played) {
            return 'No such card: a card is a rank (7 8 9 T J Q K A) then a suit (S H D C), for example QH.';
        }
        try {
            return $this->established(($this->tryWhatIf)($this->conversation->record(), $this->conversation->seat(), $trick, $played)->toArray());
        } catch (EngineRefused $refused) {
            // the record itself was accepted before the model was asked: what is refused here is the card
            return 'The engine refused: '.$refused->getMessage().'. Ask expert_choice for the cards the player could play in that trick.';
        }
    }

    public function expertBids(): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }

        return $this->established(($this->reviewBids)($this->conversation->record(), $this->conversation->seat(), $this->conversation->lang(), $this->conversation->card())->toArray());
    }

    public function biggestSwings(): string
    {
        if (!$this->conversation->mayLook()) {
            return self::NO_MORE;
        }

        return $this->established(($this->rankSwings)($this->conversation->record(), $this->conversation->seat())->toArray());
    }

    /**
     * The result as the model reads it: itself, and the name of each of its cards in the player's
     * language. The engine writes "QC"; a player reads "dame de trèfle", and the model is not left
     * to translate one into the other. The facts of the conversation are the result alone.
     *
     * It is written here as JSON, with every letter as it is: left to the agent, the names would
     * reach the model as escape codes.
     *
     * @param array<string, mixed> $result
     */
    private function established(array $result): string
    {
        $this->conversation->establish($result);
        preg_match_all('/"([789TJQKA][SHDC])"/', json_encode($result, \JSON_THROW_ON_ERROR), $found);
        $names = [];
        foreach (array_unique($found[1]) as $card) {
            $names[$card] = CardVocabulary::name($card, $this->conversation->lang()->value);
        }

        return json_encode([] === $names ? $result : [...$result, 'names' => $names], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private static function noSuchTrick(int $n): string
    {
        return \sprintf('No trick %d: a deal has tricks %d to %d.', $n, TrickNumber::FIRST, TrickNumber::LAST);
    }
}
