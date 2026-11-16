<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Suit;

/**
 * The table as a seat could read it at its turn in one trick: the cards that have fallen, those the
 * three others still hold, what they have shown, the points made so far, and what each card the seat
 * may play is there. It is what a player counts in their head, and what explains the expert's choice.
 * Nothing in it comes from a hidden hand.
 */
final readonly class TableReading
{
    /**
     * @param bool                                                                           $taking  whether the seat's team took the contract
     * @param array<string, list<string>>                                                    $fallen  the cards of the earlier tricks, by suit, the strongest first
     * @param array<string, list<string>>                                                    $out     the cards the seat has not seen, by suit, the strongest first
     * @param array{fallen: int, onTable: int, inHand: int, out: int}                        $trumps  where the eight trumps are
     * @param list<array{suit: string, card: string, mine: bool}>                            $masters per suit, the strongest card not yet played, and whether the seat holds it
     * @param list<array{seat: int, suit: string}>                                           $voids   seats that have shown they hold no card of a suit still out
     * @param int|null                                                                       $holding the seat holding the trick; null when the seat leads
     * @param array{us: int, them: int, onTable: int, inHands: int}                          $points  card points won by each team, on the table, still in the hands
     * @param list<array{card: string, trump: bool, master: bool, holds: bool, points: int}> $legal   what each card the seat may play is, there
     */
    public function __construct(
        public Suit $trump,
        public bool $taking,
        public array $fallen,
        public array $out,
        public array $trumps,
        public array $masters,
        public array $voids,
        public ?int $holding,
        public array $points,
        public array $legal,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trump' => $this->trump->value,
            'taking' => $this->taking,
            'fallen' => $this->fallen,
            'out' => $this->out,
            'trumps' => $this->trumps,
            'masters' => $this->masters,
            'voids' => $this->voids,
            'holding' => $this->holding,
            'points' => $this->points,
            'legal' => $this->legal,
        ];
    }
}
