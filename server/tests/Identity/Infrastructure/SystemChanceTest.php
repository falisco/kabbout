<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Infrastructure\SystemChance;
use PHPUnit\Framework\TestCase;

final class SystemChanceTest extends TestCase
{
    public function testChanceStaysUnderTheLimitItIsGiven(): void
    {
        $chance = new SystemChance();

        self::assertSame(0, $chance->below(1));
        for ($draw = 0; $draw < 100; ++$draw) {
            self::assertContains($chance->below(3), [0, 1, 2]);
        }
    }
}
