<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;

/** A memory for tests: the conversations in a list, for the test to read. */
final class ListedMemory implements CoachMemory
{
    /** @var array<string, list<Exchange>> */
    public array $threads = [];

    public function earlier(string $thread): array
    {
        return $this->threads[$thread] ?? [];
    }

    public function remember(string $thread, Exchange $exchange): void
    {
        $this->threads[$thread][] = $exchange;
    }
}
