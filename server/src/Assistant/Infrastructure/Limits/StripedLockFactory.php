<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Limits;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Locks for the coach's counts, a fixed number of them. A lock per visitor would be a file per visitor,
 * kept for ever by a store made of files; here every count takes one of STRIPES locks, by its name.
 * Two visitors may share a lock: one of them waits the few milliseconds a count takes, and no count is wrong.
 */
final class StripedLockFactory extends LockFactory
{
    public const int STRIPES = 64;

    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
    {
        return parent::createLock('coach.'.(crc32($resource) % self::STRIPES), $ttl, $autoRelease);
    }
}
