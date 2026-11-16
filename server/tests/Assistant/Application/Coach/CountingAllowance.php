<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Coach\CoachAllowance;
use Kabbout\Assistant\Application\Coach\CoachResting;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Assistant\Domain\Visitor;

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

    public function pace(Visitor $visitor): void
    {
        $this->asked[] = 'pace';
    }

    public function day(): void
    {
        if ($this->resting) {
            throw new CoachResting();
        }
        $this->asked[] = 'day';
    }

    public function take(Visitor $visitor, string $deal): int
    {
        if ($this->left <= 0) {
            throw new DealSpent();
        }
        $this->asked[] = 'take';

        return --$this->left;
    }

    public function giveBack(Visitor $visitor, string $deal): int
    {
        $this->asked[] = 'giveBack';

        return ++$this->left;
    }
}
