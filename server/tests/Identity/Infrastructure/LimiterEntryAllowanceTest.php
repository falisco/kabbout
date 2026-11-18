<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Infrastructure\LimiterEntryAllowance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class LimiterEntryAllowanceTest extends TestCase
{
    private WatchedCache $journal;
    private WatchedLocks $locks;

    public function testSoManyGuestsComeInFromOneAddressInAMinuteAndTheNextIsToldWhenToComeBack(): void
    {
        $allowance = $this->allowance(2);
        $home = NetworkAddress::of('203.0.113.1');
        $allowance->pace($home);
        $allowance->pace($home);

        try {
            $allowance->pace($home);
            self::fail('A third guest came in.');
        } catch (EnteredTooOften $tooOften) {
            self::assertGreaterThanOrEqual(1, $tooOften->retryAfter);
            // a sliding window: within the minute that runs, or the next
            self::assertLessThanOrEqual(120, $tooOften->retryAfter);
        }
        // the count is one address's: from elsewhere a guest still comes in
        $allowance->pace(NetworkAddress::of('203.0.113.2'));
        $this->addToAssertionCount(1);
    }

    public function testEachGuestIsCountedUnderTheLockSoThatGuestsAtTheSameInstantAreCountedOneByOne(): void
    {
        $this->allowance(2)->pace(NetworkAddress::of('203.0.113.1'));

        self::assertSame(['lock', 'unlock'], $this->journal->journal);
    }

    public function testWhenTheLockCannotBeTakenNobodyIsCountedAndNobodyComesIn(): void
    {
        $allowance = $this->allowance(2);
        $this->locks->broken = true;

        $this->expectException(PlayersUnavailable::class);

        $allowance->pace(NetworkAddress::of('203.0.113.1'));
    }

    private function allowance(int $perMinute): LimiterEntryAllowance
    {
        $this->journal = new WatchedCache(new MockClock());
        $this->locks = new WatchedLocks($this->journal);
        $limiter = new RateLimiterFactory(['id' => 'guests', 'policy' => 'sliding_window', 'limit' => $perMinute, 'interval' => '1 minute'], new InMemoryStorage());

        return new LimiterEntryAllowance($limiter, new LockFactory($this->locks));
    }
}
