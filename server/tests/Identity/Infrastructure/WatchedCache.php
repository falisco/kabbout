<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Clock\ClockInterface;

/** A cache pool for tests, in memory: it writes down each write and each clearing, and can be made to refuse writes as a full disk does. */
final class WatchedCache extends ArrayAdapter implements PruneableInterface
{
    /** @var list<string> what happened, in order: 'write' and 'prune' here, 'lock' and 'unlock' from WatchedLocks */
    public array $journal = [];

    /** whether writes are refused */
    public bool $full = false;

    public function __construct(ClockInterface $clock)
    {
        parent::__construct(clock: $clock);
    }

    public function prune(): bool
    {
        $this->journal[] = 'prune';

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($this->full) {
            return false;
        }
        $this->journal[] = 'write';

        return parent::save($item);
    }
}
