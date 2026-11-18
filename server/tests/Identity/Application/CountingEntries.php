<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\EntryAllowance;
use Kabbout\Identity\Domain\NetworkAddress;

/** An allowance for tests: it lets so many guests in, and writes down where each one asked from. */
final class CountingEntries implements EntryAllowance
{
    /** @var list<string> the addresses guests asked to come in from, in order */
    public array $from = [];

    public function __construct(private int $left = 99)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        if ($this->left <= 0) {
            throw new EnteredTooOften(42);
        }
        --$this->left;
        $this->from[] = $from->key;
    }
}
