<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Card;

/**
 * A deal finished twice by the same four expert robots: from the card a seat really played in a trick,
 * and from another card. The difference comes from the card alone, not from what was played after it.
 */
final readonly class WhatIf
{
    /** @param int $difference what the other card changes for the seat's team on the score sheet, the other team's change taken off */
    public function __construct(
        public TrickNumber $trick,
        public Card $played,
        public Card $card,
        public Ending $asPlayed,
        public Ending $instead,
        public int $difference,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trick' => $this->trick->value,
            'played' => $this->played->code(),
            'card' => $this->card->code(),
            'asPlayed' => $this->asPlayed->toArray(),
            'instead' => $this->instead->toArray(),
            'difference' => $this->difference,
        ];
    }
}
