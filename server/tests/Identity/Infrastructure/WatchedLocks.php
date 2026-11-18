<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/** A lock store for tests: it writes each lock taken and given back in the journal of the cache, and can be broken. */
final class WatchedLocks implements PersistingStoreInterface
{
    /** whether a lock can be taken at all */
    public bool $broken = false;

    private InMemoryStore $store;

    public function __construct(private readonly WatchedCache $cache)
    {
        $this->store = new InMemoryStore();
    }

    public function save(Key $key): void
    {
        $this->broken && throw new LockAcquiringException('The lock file cannot be written.');
        $this->store->save($key);
        $this->cache->journal[] = 'lock';
    }

    public function delete(Key $key): void
    {
        $this->store->delete($key);
        $this->cache->journal[] = 'unlock';
    }

    public function exists(Key $key): bool
    {
        return $this->store->exists($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->store->putOffExpiration($key, $ttl);
    }
}
