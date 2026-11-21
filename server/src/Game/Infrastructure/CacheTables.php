<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\InvalidConventionCard;
use Kabbout\Game\Domain\Exception\InvalidDealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use Kabbout\Game\Domain\Table\Tables;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockFailed;
use Symfony\Component\Lock\LockFactory;

/**
 * The tables in a cache pool of their own, on disk. A table is one entry, written whole after each
 * action of its player. It holds the four hands and the seed the cards come from: the pool is the
 * server's, and nothing reads it but this class.
 *
 * An entry is kept a day after it was last written, much longer than a table stays open
 * (Table::SECONDS_OPEN): a player who comes back to a table left too long is told it has expired,
 * which could not be told from a table that never was if the entry went when the table closed.
 *
 * What plays at a table holds one of a fixed number of locks, chosen by the table's name: a lock per
 * table would be a file per table, kept for ever by a store made of files. Two tables may share a
 * lock; one of them waits the moment a move takes.
 */
final readonly class CacheTables implements Tables
{
    private const int SECONDS_KEPT = 24 * 3600;
    private const int LOCKS = 64;
    /** a move is three robots at most between two turns; a lock nobody released is given up after this long */
    private const float LOCK_SECONDS = 60.0;

    /**
     * @param int $pruneOneIn one write in this many also clears what has expired from the pool: a file
     *                        cache never forgets by itself
     */
    public function __construct(
        #[Autowire(service: 'cache.tables')] private CacheItemPoolInterface $cache,
        private LockFactory $locks,
        private int $pruneOneIn = 200,
    ) {
    }

    public function find(TableId $id): ?Table
    {
        try {
            $kept = $this->cache->getItem(self::key($id))->get();
        } catch (CacheException $failed) {
            throw new TablesUnavailable($failed);
        }
        if (!\is_array($kept)) {
            return null;
        }
        [$owner, $seat, $level, $secret, $moves, $state, $lastDeal, $playedAt, $card] = $kept + array_fill(0, 9, null);
        try {
            return new Table(
                $id,
                Owner::of(\is_string($owner) ? $owner : ''),
                Seat::from(\is_int($seat) ? $seat : -1),
                Level::from(\is_string($level) ? $level : ''),
                Seed::fromHex(\is_string($secret) ? $secret : ''),
                \is_int($moves) ? $moves : throw new \InvalidArgumentException('No count of moves.'),
                new GameState(\is_array($state) ? self::named($state) : []),
                \is_array($lastDeal) ? DealRecord::fromArray($lastDeal) : null,
                \is_int($playedAt) ? $playedAt : throw new \InvalidArgumentException('No time.'),
                \is_array($card) ? ConventionCard::fromArray($card) : null,
            );
        } catch (\InvalidArgumentException|\ValueError|InvalidDealRecord|InvalidConventionCard) {
            // what the pool holds that is no table is no table
            return null;
        }
    }

    public function save(Table $table): void
    {
        try {
            $item = $this->cache->getItem(self::key($table->id));
            $item->set([
                $table->owner->toString(),
                $table->seat->value,
                $table->level->value,
                $table->secret->toHex(),
                $table->moves,
                $table->state->toEngineArray(),
                $table->lastDeal?->toArray(),
                $table->playedAt,
                $table->card?->toArray(),
            ]);
            $item->expiresAfter(self::SECONDS_KEPT);
            $saved = $this->cache->save($item);
        } catch (CacheException $failed) {
            throw new TablesUnavailable($failed);
        }
        if (!$saved) {
            throw new TablesUnavailable();
        }
        if ($this->cache instanceof PruneableInterface && 0 === random_int(0, max(0, $this->pruneOneIn - 1))) {
            $this->cache->prune();
        }
    }

    public function alone(TableId $id, \Closure $play): mixed
    {
        try {
            $lock = $this->locks->createLock('tables.'.(crc32($id->toString()) % self::LOCKS), self::LOCK_SECONDS);
            $lock->acquire(true);
        } catch (LockFailed $failed) {
            throw new TablesUnavailable($failed);
        }
        try {
            return $play();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param array<array-key, mixed> $state
     *
     * @return array<string, mixed>
     */
    private static function named(array $state): array
    {
        return array_filter($state, \is_string(...), \ARRAY_FILTER_USE_KEY);
    }

    private static function key(TableId $id): string
    {
        return 'table.'.$id->toString();
    }
}
