<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure;

use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\EntryAllowance;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockFailed;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The count of new guests, a Symfony rate limiter (config/packages/framework.yaml) kept in the players'
 * own cache pool. It is changed under one lock, so guests asked for at the same instant are counted one
 * by one. One lock for every address: coming in is rare, and a lock per address would be a file per address.
 */
final readonly class LimiterEntryAllowance implements EntryAllowance
{
    private const string LOCK = 'identity.entering';

    public function __construct(private RateLimiterFactoryInterface $guestsLimiter, private LockFactory $locks)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        try {
            $lock = $this->locks->createLock(self::LOCK);
            $lock->acquire(true);
            try {
                $limit = $this->guestsLimiter->create($from->key)->consume();
            } finally {
                $lock->release();
            }
        } catch (LockFailed $failed) {
            throw new PlayersUnavailable($failed);
        }
        if (!$limit->isAccepted()) {
            throw new EnteredTooOften(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
