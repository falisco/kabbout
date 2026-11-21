<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Table;

/**
 * Whose a table is, as the game knows it: a name that means nothing here and is compared, nothing
 * more. Who that is, and how they proved it, is another context's to know.
 */
final readonly class Owner
{
    private function __construct(private string $id)
    {
    }

    public static function of(string $id): self
    {
        return '' !== $id ? new self($id) : throw new \InvalidArgumentException('A table is somebody\'s.');
    }

    public function is(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function toString(): string
    {
        return $this->id;
    }
}
