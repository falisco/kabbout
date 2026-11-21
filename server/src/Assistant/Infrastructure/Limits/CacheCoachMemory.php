<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Limits;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Identity\Domain\PlayerId;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The conversations in the coach's cache pool, an hour each: the time a deal is talked about. Lost with
 * the cache, which costs a player nothing but the thread of a second question.
 */
final readonly class CacheCoachMemory implements CoachMemory
{
    private const int SECONDS_KEPT = 3600;

    public function __construct(#[Autowire(service: 'cache.coach')] private CacheItemPoolInterface $cache)
    {
    }

    public function earlier(PlayerId $player, string $deal): array
    {
        $kept = $this->cache->getItem(self::key($player, $deal))->get();
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

    public function remember(PlayerId $player, string $deal, Exchange $exchange): void
    {
        $all = \array_slice([...$this->earlier($player, $deal), $exchange], -self::MAX_EXCHANGES);
        $item = $this->cache->getItem(self::key($player, $deal));
        $item->set(array_map(static fn (Exchange $e): array => [$e->question, $e->answer], $all));
        $item->expiresAfter(self::SECONDS_KEPT);
        $this->cache->save($item);
    }

    /** a cache key holds no reserved character, whatever names the deal */
    private static function key(PlayerId $player, string $deal): string
    {
        return 'coach.thread.'.hash('sha256', $player->toString().'|'.$deal);
    }
}
