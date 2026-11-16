<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Card;

/**
 * A decision the expert would have made otherwise, and what it cost on average over the deals the
 * expert imagines. It is a loss in expectation: a bad decision, whatever the cards then did.
 */
final readonly class Swing
{
    public function __construct(public TrickNumber $trick, public Card $played, public Card $choice, public int $loss)
    {
    }

    /** @return array{trick: int, played: string, choice: string, loss: int} */
    public function toArray(): array
    {
        return ['trick' => $this->trick->value, 'played' => $this->played->code(), 'choice' => $this->choice->code(), 'loss' => $this->loss];
    }
}
