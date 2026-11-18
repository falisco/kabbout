<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * Who a player is, for the server alone: 128 random bits that say nothing and are never shown. What the
 * server keeps about a person is kept under this, so a guest who becomes a member one day keeps it all.
 */
final readonly class PlayerId
{
    private const string PATTERN = '/^[0-9a-f]{32}$/D';

    /** @param non-empty-string $hex */
    private function __construct(private string $hex)
    {
    }

    public static function random(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    /** @param string $hex 32 lower-case hexadecimal digits */
    public static function fromString(string $hex): self
    {
        return '' !== $hex && 1 === preg_match(self::PATTERN, $hex) ? new self($hex) : throw new \InvalidArgumentException('A player id is 32 hexadecimal digits.');
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }

    /** @return non-empty-string */
    public function toString(): string
    {
        return $this->hex;
    }
}
