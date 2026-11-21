<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Card;

/**
 * A decision the expert would have made otherwise, and what it cost on average over the deals the
 * expert imagines. It is a loss in expectation: a bad decision, whatever the cards then did.
 *
 * Only a `clear` swing is a mistake. Below that the expert leans to another card, by less than its
 * imagined deals can tell apart and without what a player reads in a lead or in the auction.
 */
final readonly class Swing
{
    /**
     * @param array{better: 'expert'|'played'|'same', by: int}|null $inThisDeal in the deal as it was dealt, finished by four experts: which of the
     *                                                                          two cards ended better for the seat's team, and by how many
     *                                                                          score-sheet points. null when not worked out
     */
    public function __construct(public TrickNumber $trick, public Card $played, public Card $choice, public int $loss, public bool $clear, public ?array $inThisDeal)
    {
    }

    /** @return array{trick: int, played: string, choice: string, loss: int, clear: bool, inThisDeal: array{better: 'expert'|'played'|'same', by: int}|null} */
    public function toArray(): array
    {
        return [
            'trick' => $this->trick->value,
            'played' => $this->played->code(),
            'choice' => $this->choice->code(),
            'loss' => $this->loss,
            'clear' => $this->clear,
            'inThisDeal' => $this->inThisDeal,
        ];
    }
}
