<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Allowance\DaySpent;
use Kabbout\Assistant\Application\Coach\CoachAllowance;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Identity\Domain\NetworkAddress;

/** An allowance for tests: it refuses nothing but a spent deal, and writes down what it was asked. */
final class CountingAllowance implements CoachAllowance
{
    /** @var list<string> what was asked, in order */
    public array $asked = [];

    /** whether the questions of the day are spent */
    public bool $resting = false;

    public function __construct(private int $left = 2)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        $this->asked[] = 'pace';
    }

    public function day(): void
    {
        if ($this->resting) {
            throw new DaySpent();
        }
        $this->asked[] = 'day';
    }

    public function take(NetworkAddress $from, string $deal): int
    {
        if ($this->left <= 0) {
            throw new DealSpent();
        }
        $this->asked[] = 'take';

        return --$this->left;
    }

    public function giveBack(NetworkAddress $from, string $deal): int
    {
        $this->asked[] = 'giveBack';

        return ++$this->left;
    }
}
