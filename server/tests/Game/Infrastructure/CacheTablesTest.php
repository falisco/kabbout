<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure;

use Kabbout\Game\Application\Table\Table;
use Kabbout\Game\Application\Table\TableId;
use Kabbout\Game\Application\Table\TablesUnavailable;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Infrastructure\CacheTables;
use Kabbout\Identity\Domain\PlayerId;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\PruneableInterface;

final class CacheTablesTest extends TestCase
{
    public function testATableComesBackAsItWasKept(): void
    {
        $pool = new ArrayAdapter();
        $tables = new CacheTables($pool);
        $table = self::table(self::deal());

        $tables->save($table);

        self::assertEquals($table, $tables->find($table->id));
        self::assertNull($tables->find(TableId::random()));
        // and a table nobody has finished a deal at
        $tables->save($bare = self::table(null));
        self::assertEquals($bare, $tables->find($bare->id));
        self::assertNull($tables->find($bare->id)?->lastDeal);
    }

    public function testWhatThePoolHoldsThatIsNoTableIsNoTable(): void
    {
        $pool = new ArrayAdapter();
        $tables = new CacheTables($pool);
        $id = TableId::random();

        foreach (['a table', [], ['nobody', 0, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], null, 1], [str_repeat('a', 32), 9, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], null, 1], [str_repeat('a', 32), 0, 'easy', str_repeat('a', 32), 0, ['phase' => 'bidding', 'turn' => 0], ['no' => 'deal'], 1], [str_repeat('a', 32), 0, 'easy', str_repeat('a', 32), 0, 'no game', null, 1]] as $kept) {
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
        (new CacheTables($full))->save(self::table(null));
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

        (new CacheTables($pool, pruneOneIn: 1))->save(self::table(null));

        self::assertSame(1, $pool->pruned);
    }

    private static function table(?DealRecord $lastDeal): Table
    {
        return new Table(
            TableId::tryFrom(null === $lastDeal ? str_repeat('b', 32) : str_repeat('a', 32)) ?? TableId::random(),
            PlayerId::fromString(str_repeat('1', 32)),
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
