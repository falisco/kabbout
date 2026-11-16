<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

/** Which of the eight tricks of a deal, counted from 1 as players count them. */
final readonly class TrickNumber
{
    public const int FIRST = 1;
    public const int LAST = 8;

    private function __construct(public int $value)
    {
    }

    public static function tryFrom(int $value): ?self
    {
        return $value >= self::FIRST && $value <= self::LAST ? new self($value) : null;
    }

    public static function of(int $value): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException(\sprintf('A deal has tricks %d to %d, not %d.', self::FIRST, self::LAST, $value));
    }
}
