<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Seat;

/** One trick as it was played, seen from one seat. */
final readonly class TrickReview
{
    /**
     * @param list<array{seat: int, card: string}> $plays  the four cards in the order they were played
     * @param int                                  $points the card points of the four cards; the ten of the last trick is not in it
     * @param bool                                 $won    whether the seat's team won it
     * @param Card                                 $played the card the seat put in it
     */
    public function __construct(
        public TrickNumber $trick,
        public Seat $leader,
        public array $plays,
        public Seat $winner,
        public int $points,
        public bool $won,
        public Card $played,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trick' => $this->trick->value,
            'leader' => $this->leader->value,
            'plays' => $this->plays,
            'winner' => $this->winner->value,
            'points' => $this->points,
            'won' => $this->won,
            'played' => $this->played->code(),
        ];
    }
}
