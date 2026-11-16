<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

/** How a deal ends, seen from one seat's team. */
final readonly class Ending
{
    public function __construct(public bool $made, public Side $us, public Side $them)
    {
    }

    /** @return array{made: bool, us: array{tricks: int, cardPoints: int, gained: int}, them: array{tricks: int, cardPoints: int, gained: int}} */
    public function toArray(): array
    {
        return ['made' => $this->made, 'us' => $this->us->toArray(), 'them' => $this->them->toArray()];
    }
}
