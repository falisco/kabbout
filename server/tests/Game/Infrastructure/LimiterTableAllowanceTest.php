<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure;

use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Infrastructure\LimiterTableAllowance;
use Kabbout\Identity\Domain\NetworkAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class LimiterTableAllowanceTest extends TestCase
{
    public function testSoManyCallsComeFromOneAddressInAMinuteAndTheNextIsToldWhenToComeBack(): void
    {
        $allowance = new LimiterTableAllowance(new RateLimiterFactory(['id' => 'tables', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()));
        $home = NetworkAddress::of('203.0.113.1');
        $allowance->pace($home);
        $allowance->pace($home);

        try {
            $allowance->pace($home);
            self::fail('A third call went through.');
        } catch (PlayedTooFast $tooFast) {
            self::assertGreaterThanOrEqual(1, $tooFast->retryAfter);
            self::assertLessThanOrEqual(120, $tooFast->retryAfter);
        }
        // the count is one address's
        $allowance->pace(NetworkAddress::of('203.0.113.2'));
        $this->addToAssertionCount(1);
    }
}
