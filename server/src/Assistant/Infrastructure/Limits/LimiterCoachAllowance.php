<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Limits;

use Kabbout\Assistant\Application\Allowance\AskedTooOften;
use Kabbout\Assistant\Application\Allowance\DaySpent;
use Kabbout\Assistant\Application\Coach\CoachAllowance;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Identity\Domain\NetworkAddress;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The three counts, kept in the coach's own cache pool. The pace and the day are Symfony rate limiters
 * (config/packages/framework.yaml). The questions of a deal are counted here, because a question that
 * cost nothing is given back, which a rate limiter does not do. Every count is changed under a lock
 * (one of a fixed number, see StripedLockFactory): two questions sent at the same instant are counted as two.
 */
final readonly class LimiterCoachAllowance implements CoachAllowance
{
    /** a deal is talked about right after it: its count is forgotten after this long */
    private const int DEAL_SECONDS = 3600;
    private const float LOCK_SECONDS = 5.0;

    /**
     * @param int $pruneOneIn one question in this many also clears what has expired from the pool: a
     *                        file cache never forgets by itself, and a visitor is a new key every time
     */
    public function __construct(
        private RateLimiterFactoryInterface $coachLimiter,
        private RateLimiterFactoryInterface $coachDayLimiter,
        #[Autowire(service: 'cache.coach')] private CacheItemPoolInterface $pool,
        private StripedLockFactory $locks,
        #[Autowire(env: 'int:KABBOUT_COACH_PER_DEAL')] private int $perDeal,
        private int $pruneOneIn = 200,
    ) {
    }

    public function pace(NetworkAddress $from): void
    {
        $limit = $this->coachLimiter->create($from->key)->consume();
        if (!$limit->isAccepted()) {
            throw new AskedTooOften(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    public function day(): void
    {
        if ($this->pool instanceof PruneableInterface && 0 === random_int(0, max(0, $this->pruneOneIn - 1))) {
            $this->pool->prune();
        }
        if (!$this->coachDayLimiter->create('all')->consume()->isAccepted()) {
            throw new DaySpent();
        }
    }

    public function take(NetworkAddress $from, string $deal): int
    {
        return $this->count($from, $deal, function (int $asked): int {
            return $asked < $this->perDeal ? $asked + 1 : throw new DealSpent();
        });
    }

    public function giveBack(NetworkAddress $from, string $deal): int
    {
        return $this->count($from, $deal, static fn (int $asked): int => max(0, $asked - 1));
    }

    /**
     * @param callable(int): int $change the questions asked so far, to the questions asked now
     *
     * @return int how many are left
     */
    private function count(NetworkAddress $from, string $deal, callable $change): int
    {
        $key = 'coach.deal.'.hash('sha256', $from->key.'|'.$deal);
        $lock = $this->locks->createLock($key, self::LOCK_SECONDS);
        $lock->acquire(true);
        try {
            $item = $this->pool->getItem($key);
            $asked = $item->get();
            $asked = $change(\is_int($asked) ? $asked : 0);
            $this->pool->save($item->set($asked)->expiresAfter(self::DEAL_SECONDS));

            return max(0, $this->perDeal - $asked);
        } finally {
            $lock->release();
        }
    }
}
