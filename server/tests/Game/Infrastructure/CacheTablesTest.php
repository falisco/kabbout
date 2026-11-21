<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Game\Infrastructure\CacheTables;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class CacheTablesTest extends TestCase
{
    public function testATableComesBackAsItWasKept(): void
    {
        $pool = new ArrayAdapter();
        $tables = new CacheTables($pool, self::locks());
        $table = self::table(self::deal());

        $tables->save($table);

        self::assertEquals($table, $tables->find($table->id));
        self::assertNull($tables->find(TableId::random()));
        // and a table nobody has finished a deal at
        $tables->save($bare = self::table(null));
        self::assertEquals($bare, $tables->find($bare->id));
        self::assertNull($tables->find($bare->id)?->lastDeal);
        self::assertNull($tables->find($bare->id)?->card);
    }

    public function testTheCardThePairBidsByIsKeptWithTheTable(): void
    {
        $pool = new ArrayAdapter();
        $tables = new CacheTables($pool, self::locks());
        $bold = ConventionCard::ready('bold');
        $table = self::table(self::deal());
        $withCard = new Table($table->id, $table->owner, $table->seat, $table->level, $table->secret, $table->moves, $table->state, $table->lastDeal, $table->playedAt, $bold);

        $tables->save($withCard);

        self::assertEquals($withCard, $tables->find($table->id));
        // and it stays the table's when something is played at it
        self::assertSame($bold, $withCard->after($table->state, 18, null, 1_795_000_001)->card);
        // a table kept with a card that is none is no table: nothing is played by a card nobody can read
        $kept = $pool->getItem('table.'.$table->id->toString());
        $stored = $kept->get();
        self::assertIsArray($stored);
        $stored[8] = ['jack' => 500];
        $pool->save($kept->set($stored));
        self::assertNull($tables->find($table->id));
    }

    public function testWhatThePoolHoldsThatIsNoTableIsNoTable(): void
    {
        $pool = new ArrayAdapter();
        $tables = new CacheTables($pool, self::locks());
        $id = TableId::random();

        foreach (['a table', [], ['', 0, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], null, 1], [str_repeat('a', 32), 9, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], null, 1], [str_repeat('a', 32), 0, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], ['no' => 'deal'], 1], [str_repeat('a', 32), 0, 'easy', str_repeat('a', 32), 0, 'no game', null, 1]] as $kept) {
            $pool->save($pool->getItem('table.'.$id->toString())->set($kept));
            self::assertNull($tables->find($id));
        }
    }

    public function testATableThatCouldNotBeWrittenIsSaid(): void
    {
        $full = new class extends ArrayAdapter {
            public function save(CacheItemInterface $item): bool
            {
                return false;
            }
        };

        $this->expectException(TablesUnavailable::class);
        (new CacheTables($full, self::locks()))->save(self::table(null));
    }

    public function testWhatHasExpiredIsClearedOnceInAWhile(): void
    {
        $pool = new class extends ArrayAdapter implements PruneableInterface {
            public int $pruned = 0;

            public function prune(): bool
            {
                ++$this->pruned;

                return true;
            }
        };

        (new CacheTables($pool, self::locks(), pruneOneIn: 1))->save(self::table(null));

        self::assertSame(1, $pool->pruned);
    }

    public function testWhatPlaysAtATableHoldsItUntilItIsDoneAndLetsGoWhateverHappens(): void
    {
        $store = new class extends InMemoryStore {
            /** @var list<string> */
            public array $journal = [];

            public function save(Key $key): void
            {
                parent::save($key);
                $this->journal[] = 'held '.$key;
            }

            public function delete(Key $key): void
            {
                parent::delete($key);
                $this->journal[] = 'let go '.$key;
            }
        };
        $tables = new CacheTables(new ArrayAdapter(), new LockFactory($store));
        $id = TableId::random();

        $played = $tables->alone($id, static function () use ($store): string {
            self::assertCount(1, $store->journal, 'Nothing holds the table while something plays at it.');

            return 'played';
        });
        self::assertSame(['played', 2], [$played, \count($store->journal)]);
        // the lock is one of a fixed number, named after the table: the same one each time for the same table
        self::assertMatchesRegularExpression('/^held tables\.([0-9]|[1-5][0-9]|6[0-3])$/D', $store->journal[0]);
        self::assertSame(str_replace('held', 'let go', $store->journal[0]), $store->journal[1]);

        $refused = null;
        try {
            $tables->alone($id, static fn (): never => throw new \DomainException('refused'));
        } catch (\Throwable $thrown) {
            $refused = $thrown->getMessage();
        }
        self::assertSame('refused', $refused);
        self::assertSame([$store->journal[0], $store->journal[1], $store->journal[0], $store->journal[1]], $store->journal, 'A refused move left the table held.');
    }

    public function testATableThatCannotBeHeldIsSaid(): void
    {
        $broken = new class extends InMemoryStore {
            public function save(Key $key): void
            {
                throw new LockAcquiringException('no lock to be had');
            }
        };

        $this->expectException(TablesUnavailable::class);
        (new CacheTables(new ArrayAdapter(), new LockFactory($broken)))->alone(TableId::random(), static fn (): string => self::fail('Something played at a table nobody could hold.'));
    }

    private static function locks(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }

    private static function table(?DealRecord $lastDeal): Table
    {
        return new Table(
            TableId::tryFrom(null === $lastDeal ? str_repeat('b', 32) : str_repeat('a', 32)) ?? TableId::random(),
            Owner::of(str_repeat('1', 32)),
            Seat::Third,
            Level::Expert,
            Seed::fromHex(str_repeat('5e', 16)),
            17,
            new GameState(['phase' => 'playing', 'turn' => 2, 'hands' => [['7S'], [], [], []], 'bids' => [['seat' => 1, 'type' => 'pass', 'why' => 'a reason']]]),
            $lastDeal,
            1_795_000_000,
        );
    }

    private static function deal(): DealRecord
    {
        $trick = array_map(static fn (int $seat): array => ['seat' => $seat, 'card' => '7S'], [1, 2, 3, 0]);

        return DealRecord::fromArray(['scoring' => 'ffb', 'dealer' => 0, 'bids' => [['seat' => 1, 'type' => 'bid', 'value' => 80, 'suit' => 'S']], 'tricks' => array_fill(0, 8, $trick)]);
    }
}
