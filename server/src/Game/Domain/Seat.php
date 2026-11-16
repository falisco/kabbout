<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * One of the four places at the table, numbered as the engine numbers them, in the order of play.
 * Seats facing each other are partners: 0 and 2 against 1 and 3.
 */
enum Seat: int
{
    case First = 0;
    case Second = 1;
    case Third = 2;
    case Fourth = 3;

    public function next(): self
    {
        return self::from(($this->value + 1) % 4);
    }

    public function partner(): self
    {
        return self::from(($this->value + 2) % 4);
    }

    /** 0 for seats 0 and 2, 1 for seats 1 and 3. */
    public function team(): int
    {
        return $this->value % 2;
    }
}
