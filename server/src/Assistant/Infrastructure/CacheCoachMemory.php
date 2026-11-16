<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The conversations in the application cache, an hour each: the time a deal is talked about. Lost with
 * the cache, which costs a player nothing but the thread of a second question.
 */
final readonly class CacheCoachMemory implements CoachMemory
{
    private const int SECONDS_KEPT = 3600;

    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function earlier(string $thread): array
    {
        $kept = $this->cache->getItem(self::key($thread))->get();
        if (!\is_array($kept)) {
            return [];
        }
        $exchanges = [];
        foreach ($kept as $one) {
            if (\is_array($one) && \is_string($one[0] ?? null) && \is_string($one[1] ?? null)) {
                $exchanges[] = new Exchange($one[0], $one[1]);
            }
        }

        return $exchanges;
    }

    public function remember(string $thread, Exchange $exchange): void
    {
        $all = \array_slice([...$this->earlier($thread), $exchange], -self::MAX_EXCHANGES);
        $item = $this->cache->getItem(self::key($thread));
        $item->set(array_map(static fn (Exchange $e): array => [$e->question, $e->answer], $all));
        $item->expiresAfter(self::SECONDS_KEPT);
        $this->cache->save($item);
    }

    /** a cache key holds no reserved character, whatever names the thread */
    private static function key(string $thread): string
    {
        return 'coach.thread.'.hash('sha256', $thread);
    }
}
