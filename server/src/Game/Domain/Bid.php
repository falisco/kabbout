<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

use Kabbout\Game\Domain\Exception\InvalidBid;

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

    /**
     * A bid from its parts, as a client names them. A raise without its value or its suit is no bid;
     * whether the value is one the rules know, and whether the bid is allowed now, is for the engine.
     *
     * @throws InvalidBid
     */
    public static function of(BidType $type, ?int $value = null, ?Suit $suit = null): self
    {
        if (BidType::Raise !== $type) {
            return new self($type);
        }

        return null !== $value && null !== $suit ? new self($type, $value, $suit) : throw new InvalidBid('A bid of type "bid" names its value and its trump suit.');
    }
}
