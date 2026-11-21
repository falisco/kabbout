<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

/** What one team made of a deal: its tricks, its card points (the ten of the last trick included) and what goes on the score sheet. */
final readonly class Side
{
    public function __construct(public int $tricks, public int $cardPoints, public int $gained)
    {
    }

    /** @return array{tricks: int, cardPoints: int, gained: int} */
    public function toArray(): array
    {
        return ['tricks' => $this->tricks, 'cardPoints' => $this->cardPoints, 'gained' => $this->gained];
    }
}
