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
        $allowance = self::allowance(calls: 2, tables: 99);
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
        // the count is one address's, and the tables have a count of their own
        $allowance->pace(NetworkAddress::of('203.0.113.2'));
        $allowance->open($home);
        $this->addToAssertionCount(1);
    }

    public function testSoManyTablesAreOpenedFromOneAddressInAnHour(): void
    {
        $allowance = self::allowance(calls: 99, tables: 1);
        $home = NetworkAddress::of('203.0.113.1');
        $allowance->open($home);

        try {
            $allowance->open($home);
            self::fail('A second table was opened.');
        } catch (PlayedTooFast $tooMany) {
            // an hour's window, not a minute's
            self::assertGreaterThan(120, $tooMany->retryAfter);
        }
        $allowance->pace($home);
        $allowance->open(NetworkAddress::of('203.0.113.2'));
        $this->addToAssertionCount(1);
    }

    private static function allowance(int $calls, int $tables): LimiterTableAllowance
    {
        $storage = new InMemoryStorage();

        return new LimiterTableAllowance(
            new RateLimiterFactory(['id' => 'tables', 'policy' => 'sliding_window', 'limit' => $calls, 'interval' => '1 minute'], $storage),
            new RateLimiterFactory(['id' => 'tables_opened', 'policy' => 'sliding_window', 'limit' => $tables, 'interval' => '1 hour'], $storage),
        );
    }
}
