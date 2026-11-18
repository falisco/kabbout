<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\TableAllowance;
use Kabbout\Identity\Domain\NetworkAddress;

/** A pace for tests: it lets so many calls through, and writes down where each came from. */
final class CountingPace implements TableAllowance
{
    /** @var list<string> */
    public array $from = [];

    public function __construct(private int $left = 999)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        if ($this->left <= 0) {
            throw new PlayedTooFast(7);
        }
        --$this->left;
        $this->from[] = $from->key;
    }
}
