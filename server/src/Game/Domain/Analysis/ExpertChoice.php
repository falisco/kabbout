<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Card;

/**
 * What the expert robot would have played at a seat's turn in one trick, knowing only what the seat
 * could know then. Averages are over the deals the expert imagines, in score-sheet points for the
 * seat's team minus the other team's.
 */
final readonly class ExpertChoice
{
    /**
     * @param list<string>                            $hand    the seat's cards at that moment
     * @param list<array{seat: int, card: string}>    $onTable the cards already in the trick
     * @param bool                                    $forced  one card only was legal
     * @param int                                     $worlds  deals imagined; 0 when forced
     * @param list<array{card: string, average: int}> $cards   every legal card with its average
     * @param int                                     $loss    the expert's average minus the played card's; 0 for the same card
     */
    public function __construct(
        public TrickNumber $trick,
        public array $hand,
        public array $onTable,
        public Card $played,
        public Card $choice,
        public bool $forced,
        public int $worlds,
        public array $cards,
        public int $loss,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trick' => $this->trick->value,
            'hand' => $this->hand,
            'onTable' => $this->onTable,
            'played' => $this->played->code(),
            'choice' => $this->choice->code(),
            'forced' => $this->forced,
            'worlds' => $this->worlds,
            'cards' => $this->cards,
            'loss' => $this->loss,
        ];
    }
}
