<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * What a seat asks the engine to do: a bid, a card, or the next deal once one is over.
 * Whether it is allowed is for the engine to say, never for the caller.
 *
 * A robot's bid carries its reason, which names its cards: it goes back to the engine with the action
 * and to nobody else. A player's action has none.
 */
final readonly class Action
{
    private function __construct(
        public ActionKind $kind,
        public ?Bid $bid = null,
        public ?Card $card = null,
        public ?string $reason = null,
    ) {
    }

    public static function bid(Bid $bid, ?string $reason = null): self
    {
        return new self(ActionKind::Bid, bid: $bid, reason: $reason);
    }

    public static function play(Card $card): self
    {
        return new self(ActionKind::Play, card: $card);
    }

    public static function next(): self
    {
        return new self(ActionKind::Next);
    }

    /** The same action as the table hears it: without the reason a robot gave for it. */
    public function withoutReason(): self
    {
        return new self($this->kind, $this->bid, $this->card);
    }
}
