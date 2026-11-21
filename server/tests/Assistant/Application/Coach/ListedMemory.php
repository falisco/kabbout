<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Identity\Domain\PlayerId;

/** A memory for tests: the conversations in a list, by player and deal, for the test to read. */
final class ListedMemory implements CoachMemory
{
    /** @var array<string, list<Exchange>> */
    public array $threads = [];

    public function earlier(PlayerId $player, string $deal): array
    {
        return $this->threads[$player->toString().'|'.$deal] ?? [];
    }

    public function remember(PlayerId $player, string $deal, Exchange $exchange): void
    {
        $this->threads[$player->toString().'|'.$deal][] = $exchange;
    }
}
