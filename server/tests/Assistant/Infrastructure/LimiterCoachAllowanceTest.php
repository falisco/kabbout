<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure;

use Kabbout\Assistant\Application\Coach\AskedTooOften;
use Kabbout\Assistant\Application\Coach\CoachResting;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Assistant\Domain\Visitor;
use Kabbout\Assistant\Infrastructure\LimiterCoachAllowance;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class LimiterCoachAllowanceTest extends TestCase
{
    public function testAVisitorHasSoManyQuestionsOnADealAndAnotherDealOrVisitorHasItsOwn(): void
    {
        $allowance = self::allowance(perDeal: 2);
        [$ana, $ben] = [Visitor::atAddress('203.0.113.1'), Visitor::atAddress('203.0.113.2')];

        self::assertSame(1, $allowance->take($ana, 'deal'));
        self::assertSame(0, $allowance->take($ana, 'deal'));
        self::assertSame(1, $allowance->take($ana, 'another deal'));
        self::assertSame(1, $allowance->take($ben, 'deal'));
        $this->expectException(DealSpent::class);
        $allowance->take($ana, 'deal');
    }

    public function testAQuestionGivenBackMayBeAskedAgainAndNothingIsGivenThatWasNotTaken(): void
    {
        $allowance = self::allowance(perDeal: 2);
        $ana = Visitor::atAddress('203.0.113.1');

        self::assertSame(2, $allowance->giveBack($ana, 'deal'));
        $allowance->take($ana, 'deal');
        $allowance->take($ana, 'deal');
        self::assertSame(1, $allowance->giveBack($ana, 'deal'));
        self::assertSame(0, $allowance->take($ana, 'deal'));
    }

    public function testAVisitorAskingTooFastIsToldWhenToComeBack(): void
    {
        $allowance = self::allowance(perMinute: 2);
        $ana = Visitor::atAddress('203.0.113.1');
        $allowance->pace($ana);
        $allowance->pace($ana);
        $allowance->pace(Visitor::atAddress('203.0.113.2'));

        try {
            $allowance->pace($ana);
            self::fail('The third question of the minute should have been refused.');
        } catch (AskedTooOften $tooOften) {
            self::assertGreaterThanOrEqual(1, $tooOften->retryAfter);
            self::assertLessThanOrEqual(61, $tooOften->retryAfter);
        }
    }

    public function testTheDayIsCountedForAllVisitorsTogether(): void
    {
        $allowance = self::allowance(perDay: 2);
        $allowance->day();
        $allowance->day();

        $this->expectException(CoachResting::class);
        $allowance->day();
    }

    public function testWhatHasExpiredIsClearedFromThePoolNowAndThen(): void
    {
        $pool = new class extends ArrayAdapter implements PruneableInterface {
            public int $pruned = 0;

            public function prune(): bool
            {
                ++$this->pruned;

                return true;
            }
        };

        // one question in one: every time
        self::allowance(pool: $pool, pruneOneIn: 1)->day();
        self::allowance(pool: $pool, pruneOneIn: 1)->day();
        self::assertSame(2, $pool->pruned);

        // a pool that cannot be cleared is left alone
        self::allowance(pool: new ArrayAdapter(), pruneOneIn: 1)->day();
    }

    private static function allowance(int $perMinute = 99, int $perDay = 99, int $perDeal = 2, ?CacheItemPoolInterface $pool = null, int $pruneOneIn = 1): LimiterCoachAllowance
    {
        $limiter = static fn (string $policy, int $limit, string $interval): RateLimiterFactory => new RateLimiterFactory(
            ['id' => $policy.$limit, 'policy' => $policy, 'limit' => $limit, 'interval' => $interval],
            new InMemoryStorage(),
        );

        return new LimiterCoachAllowance(
            $limiter('sliding_window', $perMinute, '1 minute'),
            $limiter('fixed_window', $perDay, '1 day'),
            $pool ?? new ArrayAdapter(),
            new LockFactory(new InMemoryStore()),
            $perDeal,
            $pruneOneIn,
        );
    }
}
