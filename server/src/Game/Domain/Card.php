<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * One of the thirty-two cards, written as the engine writes it: a rank then a suit, as in "JS" or "TH".
 * A code that names no card cannot become one. Whether a card may be played is for the engine to say.
 */
final readonly class Card
{
    private const string RANKS = '789TJQKA';
    /** how a card is written, for whoever must say it to a client: a rank, then a suit (Suit) */
    public const string FORMAT = '^['.self::RANKS.'][SHDC]$';

    private function __construct(private string $rank, private Suit $suit)
    {
    }

    public static function tryFromCode(string $code): ?self
    {
        if (2 !== \strlen($code) || !str_contains(self::RANKS, $code[0])) {
            return null;
        }
        $suit = Suit::tryFrom($code[1]);

        return null === $suit ? null : new self($code[0], $suit);
    }

    public static function fromCode(string $code): self
    {
        return self::tryFromCode($code) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a card.', $code));
    }

    public function code(): string
    {
        return $this->rank.$this->suit->value;
    }
}
