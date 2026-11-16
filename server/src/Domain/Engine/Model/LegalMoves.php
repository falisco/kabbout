<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Model;

/** What a seat may do now. Out of turn: nothing. */
final readonly class LegalMoves
{
    /**
     * @param list<array<string, mixed>> $bids  each as the engine writes a bid: type, and value and suit for a raise
     * @param list<string>               $cards
     * @param bool                       $next  the deal is scored: the next one may be asked for
     */
    public function __construct(public array $bids, public array $cards, public bool $next)
    {
    }

    public function isEmpty(): bool
    {
        return [] === $this->bids && [] === $this->cards && !$this->next;
    }

    /** @return array{bids: list<array<string, mixed>>, cards: list<string>, next: bool} */
    public function toArray(): array
    {
        return ['bids' => $this->bids, 'cards' => $this->cards, 'next' => $this->next];
    }
}
