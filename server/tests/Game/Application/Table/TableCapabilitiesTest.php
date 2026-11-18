<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Application\Table\ActAtTable;
use Kabbout\Game\Application\Table\FinishedDeal;
use Kabbout\Game\Application\Table\MoveRefused;
use Kabbout\Game\Application\Table\NoFinishedDeal;
use Kabbout\Game\Application\Table\NoSuchTable;
use Kabbout\Game\Application\Table\OpenTable;
use Kabbout\Game\Application\Table\OwnTable;
use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\ReadScore;
use Kabbout\Game\Application\Table\RobotsPlay;
use Kabbout\Game\Application\Table\SeeTable;
use Kabbout\Game\Application\Table\Table;
use Kabbout\Game\Application\Table\TableExpired;
use Kabbout\Game\Application\Table\Turn;
use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Infrastructure\ClockNow;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** What a player can do at a table of theirs, over an engine that only knows whose turn it is. */
final class TableCapabilitiesTest extends TestCase
{
    private TurnsEngine $engine;
    private ListedTables $tables;
    private CountingPace $pace;
    private MockClock $clock;
    private Player $ana;
    private Player $ben;

    protected function setUp(): void
    {
        $this->engine = new TurnsEngine();
        $this->tables = new ListedTables();
        $this->pace = new CountingPace();
        $this->clock = new MockClock('2026-11-18 12:00:00');
        $this->ana = Player::guest(PlayerId::random(), PlayerName::fromString('C0000001'));
        $this->ben = Player::guest(PlayerId::random(), PlayerName::fromString('C0000002'));
    }

    public function testSittingDownDealsAGameAndTheRobotsPlayUntilItIsThePlayersTurn(): void
    {
        $turn = $this->open($this->ana, Level::Expert);

        // seats 1, 2 and 3 acted, each from what a robot of the chosen level would do, and the player is to act
        self::assertSame([1, 2, 3], array_column(array_filter($this->engine->drawn, static fn (array $call): bool => 'apply' === $call[0]), 1));
        self::assertSame(['expert en', 'expert en', 'expert en'], array_column(array_filter($this->engine->drawn, static fn (array $call): bool => 'robot' === $call[0]), 2));
        self::assertSame(0, $turn->view->toArray()['turn']);
        self::assertSame([['type' => 'pass']], $turn->legal->bids);
        self::assertSame(['dealt', 'acted', 'acted', 'acted'], array_column($turn->events, 'type'));
        // a robot's reason goes back to the engine with its bid, and nowhere else
        self::assertSame(array_fill(0, 3, 'I hold the jack of spades'), $this->engine->reasons);
        self::assertStringNotContainsString('jack of spades', json_encode($turn->toArray(), \JSON_THROW_ON_ERROR));

        $kept = $this->tables->kept[$turn->table->toString()];
        self::assertTrue($kept->isOf($this->ana->id));
        self::assertFalse($kept->isOf($this->ben->id));
        self::assertSame([3, Seat::First, Level::Expert], [$kept->moves, $kept->seat, $kept->level]);
        self::assertSame(['address:203.0.113.1'], $this->pace->from);
    }

    public function testEveryCallThatDrawsHasASeedOfItsOwnAndTheSameSeedGivesTheSameOnes(): void
    {
        $seed = Seed::fromHex(str_repeat('ab', 16));
        $turn = $this->open($this->ana, seed: $seed);
        $this->act()($this->ana, self::home(), $turn->table->toString(), Action::bid(Bid::pass()));
        $first = array_column($this->engine->drawn, 3);

        self::assertCount(\count($first), array_unique($first));
        self::assertNotContains($seed->toHex(), $first, 'The secret itself is never sent to the engine.');

        $this->engine = new TurnsEngine();
        $again = $this->open($this->ben, seed: $seed);
        $this->act()($this->ben, self::home(), $again->table->toString(), Action::bid(Bid::pass()));
        self::assertSame($first, array_column($this->engine->drawn, 3));

        // and a table opened with no seed draws from a secret nobody chose
        $this->engine = new TurnsEngine();
        $this->open($this->ana);
        self::assertSame([], array_intersect($first, array_column($this->engine->drawn, 3)));
    }

    public function testThePlayerActsThenTheRobotsUntilTheDealIsOverAndTheDealIsKeptForAReview(): void
    {
        $turn = $this->open($this->ana);
        $table = $turn->table->toString();
        try {
            ($this->finished())($this->ana, self::home(), $table);
            self::fail('A deal that is not over was handed to a review.');
        } catch (NoFinishedDeal) {
        }

        // six actions in a deal: three robots, the player, two robots, and the deal is over before the player's turn comes
        $turn = $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass(), 'a reason a player has no business giving'));
        self::assertNull($this->engine->reasons[3]);
        self::assertSame('roundEnd', $turn->view->toArray()['phase']);
        self::assertTrue($turn->legal->next);
        self::assertSame(6, $this->tables->kept[$table]->moves);

        $deal = ($this->finished())($this->ana, self::home(), $table);
        self::assertSame(Seat::First, $deal->seat);
        self::assertCount(8, $deal->record->tricks);

        // the next deal is dealt, the robots play, and the deal before it is still the one a review is about
        $next = $this->act()($this->ana, self::home(), $table, Action::next());
        self::assertSame([2, 'bidding', 0], [$next->view->toArray()['round'], $next->view->toArray()['phase'], $next->view->toArray()['turn']]);
        self::assertEquals($deal, ($this->finished())($this->ana, self::home(), $table));
    }

    public function testWhatTheEngineRefusesChangesNothingAndThePlayerIsToldWhatTheyMayDo(): void
    {
        $turn = $this->open($this->ana);
        $before = $this->tables->kept[$turn->table->toString()];
        $saved = $this->tables->saved;

        try {
            $this->act()($this->ana, self::home(), $turn->table->toString(), Action::next());
            self::fail('The next deal was dealt in the middle of one.');
        } catch (MoveRefused $refused) {
            self::assertSame([['type' => 'pass']], $refused->legal->bids);
            self::assertStringContainsString('Refused: not now Nothing has changed.', $refused->getMessage());
            self::assertStringContainsString('{"bids":[{"type":"pass"}],"cards":[],"next":false}', $refused->getMessage());
        }
        self::assertSame($saved, $this->tables->saved);
        self::assertSame($before, $this->tables->kept[$turn->table->toString()]);

        try {
            $this->act()($this->ana, self::home(), $turn->table->toString(), Action::play(Card::fromCode('7S')));
            $this->act()($this->ana, self::home(), $turn->table->toString(), Action::play(Card::fromCode('7S')));
            self::fail('The player acted twice in a row.');
        } catch (MoveRefused) {
            self::assertSame($saved + 1, $this->tables->saved);
        }
    }

    public function testTheTableOfSomebodyElseAndATableThatDoesNotExistAreToldAlike(): void
    {
        $table = $this->open($this->ana)->table->toString();
        $own = $this->own();

        $refusals = [];
        foreach ([[$this->ben, $table], [$this->ben, str_repeat('0', 32)], [$this->ben, 'no name at all'], [$this->ana, strtoupper($table).'0']] as [$player, $named]) {
            try {
                $own($player, self::home(), $named);
                self::fail('A table was handed to somebody it is not the table of.');
            } catch (NoSuchTable $none) {
                $refusals[] = $none->getMessage();
            }
        }
        self::assertCount(1, array_unique($refusals));
        self::assertSame($this->tables->kept[$table], $own($this->ana, self::home(), $table));

        foreach ([$this->see(), $this->score(), $this->finished()] as $capability) {
            try {
                $capability($this->ben, self::home(), $table);
                self::fail($capability::class.' answered somebody else about a table.');
            } catch (NoSuchTable) {
            }
        }
    }

    public function testATableLeftAloneTooLongHasExpiredAndSaysSoToItsPlayerOnly(): void
    {
        $table = $this->open($this->ana)->table->toString();

        $this->clock->sleep(Table::SECONDS_OPEN);
        self::assertSame($table, $this->see()($this->ana, self::home(), $table)->table->toString());

        // playing keeps a table open: the three hours run from the last action
        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));
        $this->clock->sleep(Table::SECONDS_OPEN);
        $this->see()($this->ana, self::home(), $table);

        $this->clock->sleep(1);
        try {
            $this->see()($this->ana, self::home(), $table);
            self::fail('A table left alone too long is still open.');
        } catch (TableExpired $expired) {
            self::assertStringContainsString('expired', $expired->getMessage());
            self::assertStringContainsString('3 hours', $expired->getMessage());
        }
        $this->expectException(NoSuchTable::class);
        $this->see()($this->ben, self::home(), $table);
    }

    public function testEveryCallIsCountedAgainstWhereItComesFromBeforeAnythingIsLookedAt(): void
    {
        $table = $this->open($this->ana)->table->toString();
        $this->pace = new CountingPace(0);

        foreach ([
            fn (): Turn => $this->open($this->ana),
            fn (): mixed => $this->see()($this->ana, self::home(), $table),
            fn (): mixed => $this->score()($this->ana, self::home(), $table),
            fn (): mixed => $this->finished()($this->ana, self::home(), $table),
            fn (): mixed => $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass())),
            fn (): mixed => $this->see()($this->ben, self::home(), 'no name at all'),
        ] as $call) {
            try {
                $call();
                self::fail('A call went through faster than allowed.');
            } catch (PlayedTooFast $tooFast) {
                self::assertSame(7, $tooFast->retryAfter);
                self::assertStringContainsString('7 seconds', $tooFast->getMessage());
            }
        }
        self::assertCount(1, $this->tables->kept);
    }

    public function testTheScoreIsTheCountAndNothingOfTheCards(): void
    {
        $table = $this->open($this->ana, seat: Seat::Second)->table->toString();

        $score = $this->score()($this->ana, self::home(), $table)->toArray();

        self::assertSame(['table', 'yourTeam', 'phase', 'round', 'scoring', 'scores'], array_keys($score));
        self::assertSame([$table, 1, [0, 0]], [$score['table'], $score['yourTeam'], $score['scores']]);
    }

    private function open(Player $player, Level $level = Level::Easy, Seat $seat = Seat::First, ?Seed $seed = null): Turn
    {
        return (new OpenTable($this->engine, $this->robots(), $this->pace, new ClockNow($this->clock)))($player, self::home(), $level, $seat, $seed);
    }

    private function robots(): RobotsPlay
    {
        return new RobotsPlay($this->engine, $this->tables, new ClockNow($this->clock));
    }

    private function own(): OwnTable
    {
        return new OwnTable($this->tables, $this->pace, new ClockNow($this->clock));
    }

    private function act(): ActAtTable
    {
        return new ActAtTable($this->own(), $this->engine, $this->robots());
    }

    private function see(): SeeTable
    {
        return new SeeTable($this->own(), $this->engine);
    }

    private function score(): ReadScore
    {
        return new ReadScore($this->own(), $this->engine);
    }

    private function finished(): FinishedDeal
    {
        return new FinishedDeal($this->own());
    }

    private static function home(): NetworkAddress
    {
        return NetworkAddress::of('203.0.113.1');
    }
}
