<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Domain;

use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testATableIsItsOwnersAndNobodyElses(): void
    {
        $table = self::table();

        self::assertTrue($table->isOf(Owner::of('ana')));
        self::assertFalse($table->isOf(Owner::of('ben')));
        self::assertFalse($table->isOf(Owner::of('Ana')));
        self::assertSame('ana', $table->owner->toString());
        $this->expectException(\InvalidArgumentException::class);
        Owner::of('');
    }

    public function testATableIsOverThreeHoursAfterWhatWasLastPlayedThere(): void
    {
        $table = self::table();

        self::assertFalse($table->isOverAt(1000));
        self::assertFalse($table->isOverAt(1000 + Table::SECONDS_OPEN));
        self::assertTrue($table->isOverAt(1001 + Table::SECONDS_OPEN));
    }

    public function testWhatIsPlayedLeavesTheSameTableWithItsLastFinishedDealUntilAnotherIsOver(): void
    {
        $trick = array_map(static fn (int $seat): array => ['seat' => $seat, 'card' => '7S'], [1, 2, 3, 0]);
        $deal = static fn (int $dealer): DealRecord => DealRecord::fromArray(['scoring' => 'ffb', 'dealer' => $dealer, 'bids' => [], 'tricks' => array_fill(0, 8, $trick)]);
        $state = new GameState(['phase' => 'roundEnd', 'turn' => 1]);
        $table = self::table();

        $after = $table->after($state, 9, $deal(0), 2000);
        self::assertSame([$table->id, $table->owner, $table->seat, $table->level, $table->secret], [$after->id, $after->owner, $after->seat, $after->level, $after->secret]);
        self::assertSame([$state, 9, 2000, 0], [$after->state, $after->moves, $after->playedAt, $after->lastDeal?->dealer->value]);

        // the next deal is being played: the one before it is still the last one finished
        self::assertSame(0, $after->after($state, 12, null, 3000)->lastDeal?->dealer->value);
        self::assertSame(1, $after->after($state, 20, $deal(1), 4000)->lastDeal?->dealer->value);
    }

    public function testTheNameOfATableIsThirtyTwoHexadecimalDigitsAndNothingElse(): void
    {
        $id = TableId::random();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->toString());
        self::assertNotSame($id->toString(), TableId::random()->toString());
        self::assertSame($id->toString(), TableId::tryFrom($id->toString())?->toString());
        foreach (['', 'no name at all', strtoupper($id->toString()), $id->toString().'0', $id->toString()."\n"] as $given) {
            self::assertNull(TableId::tryFrom($given));
        }
    }

    private static function table(): Table
    {
        return new Table(TableId::random(), Owner::of('ana'), Seat::Second, Level::Normal, Seed::random(), 3, new GameState(['phase' => 'bidding', 'turn' => 1]), null, 1000);
    }
}
