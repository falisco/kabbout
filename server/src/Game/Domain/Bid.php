<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * One word of the auction. Only a raise has a value and a suit, and it always has both.
 * Whether the bid is allowed now, and whether its value is one the game knows, is for the engine to say.
 */
final readonly class Bid
{
    private function __construct(public BidType $type, public ?int $value = null, public ?Suit $suit = null)
    {
    }

    public static function pass(): self
    {
        return new self(BidType::Pass);
    }

    public static function contre(): self
    {
        return new self(BidType::Contre);
    }

    public static function surcontre(): self
    {
        return new self(BidType::Surcontre);
    }

    /** @param int $value 80 to 160 by tens, or 250 for the capot */
    public static function raise(int $value, Suit $suit): self
    {
        return new self(BidType::Raise, $value, $suit);
    }
}
