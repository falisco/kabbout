<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Table;

/**
 * The name of a table: 128 random bits. It says nothing and proves nothing: a table is its player's,
 * and whoever else names it is told there is no such table.
 */
final readonly class TableId
{
    private const string PATTERN = '/^[0-9a-f]{32}$/D';

    private function __construct(private string $hex)
    {
    }

    public static function random(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    /** What somebody gives as the name of a table, if it can be one at all. */
    public static function tryFrom(string $given): ?self
    {
        return 1 === preg_match(self::PATTERN, $given) ? new self($given) : null;
    }

    public function toString(): string
    {
        return $this->hex;
    }
}
