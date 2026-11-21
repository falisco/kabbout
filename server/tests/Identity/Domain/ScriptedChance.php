<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\Chance;

/** Chance for tests: it gives the numbers it was told to, in order, and writes down what it was asked. */
final class ScriptedChance implements Chance
{
    /** @var list<int> the limits it was asked under, in order */
    public array $limits = [];

    /** @param list<int> $numbers */
    public function __construct(private array $numbers)
    {
    }

    public function below(int $limit): int
    {
        $this->limits[] = $limit;

        return array_shift($this->numbers) ?? throw new \LogicException('Chance was asked more often than the test said.');
    }
}
