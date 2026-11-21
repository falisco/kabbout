<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Application\Table\ActAtTable;
use Kabbout\Game\Application\Table\FinishedDeal;
use Kabbout\Game\Application\Table\ListMoves;
use Kabbout\Game\Application\Table\MoveRefused;
use Kabbout\Game\Application\Table\NoFinishedDeal;
use Kabbout\Game\Application\Table\NoSuchTable;
use Kabbout\Game\Application\Table\OpenTable;
use Kabbout\Game\Application\Table\OwnTable;
use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\ReadScore;
use Kabbout\Game\Application\Table\RobotsPlay;
use Kabbout\Game\Application\Table\SeeTable;
use Kabbout\Game\Application\Table\TableExpired;
use Kabbout\Game\Application\Table\Turn;
use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Infrastructure\ClockNow;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** What a player can do at a table of theirs, over an engine that only knows whose turn it is. */
final class TableCapabilitiesTest extends TestCase
{
    private TurnsEngine $engine;
    private ListedTables $tables;
    private CountingPace $pace;
    private MockClock $clock;
    private PlayerId $ana;
    private PlayerId $ben;

    protected function setUp(): void
    {
        $this->engine = new TurnsEngine();
        $this->tables = new ListedTables();
        $this->pace = new CountingPace();
        $this->clock = new MockClock('2026-11-18 12:00:00');
        $this->ana = PlayerId::random();
        $this->ben = PlayerId::random();
    }

    public function testSittingDownDealsAGameAndTheRobotsPlayUntilItIsThePlayersTurn(): void
    {
        $turn = $this->open($this->ana, Level::Expert);

        // seats 1, 2 and 3 acted, each from what a robot of the chosen level would do, and the player is to act
        self::assertSame([1, 2, 3], $this->seatsThatActed());
        self::assertSame(['expert fr', 'expert fr', 'expert fr'], array_column(array_filter($this->engine->drawn, static fn (array $call): bool => 'robot' === $call[0]), 2));
        self::assertSame(0, $turn->view->toArray()['turn']);
        self::assertSame(Seat::First, $turn->view->seat());
        self::assertSame([['type' => 'pass']], $turn->legal->bids);
        self::assertSame(['dealt', 'acted', 'acted', 'acted'], array_column($turn->events, 'type'));
        // a robot's reason goes back to the engine with its bid, and nowhere else
        self::assertSame(array_fill(0, 3, 'I hold the jack of spades'), $this->engine->reasons);
        self::assertStringNotContainsString('jack of spades', json_encode($turn->toArray(), \JSON_THROW_ON_ERROR));

        $kept = $this->tables->kept[$turn->table->toString()];
        self::assertTrue($kept->isOf(Owner::of($this->ana->toString())));
        self::assertFalse($kept->isOf(Owner::of($this->ben->toString())));
        self::assertSame([3, Seat::First, Level::Expert], [$kept->moves, $kept->seat, $kept->level]);
        self::assertSame(['address:203.0.113.1'], $this->pace->from);
        self::assertSame(1, $this->pace->opened);
    }

    public function testThePlayerSitsWhereTheyChoseAndTheRobotsPlayTheThreeOtherSeats(): void
    {
        $turn = $this->open($this->ana, seat: Seat::Third);
        $table = $turn->table->toString();

        // seat 1 speaks first: one robot, and it is seat 2's turn
        self::assertSame([1], $this->seatsThatActed());
        self::assertSame([Seat::Third, 2], [$turn->view->seat(), $turn->view->toArray()['turn']]);
        self::assertSame([['type' => 'pass']], $turn->legal->bids);
        self::assertSame(Seat::Third, $this->see()($this->ana, self::home(), $table)->seat());
        self::assertSame([['type' => 'pass']], $this->moves()($this->ana, self::home(), $table)->bids);
        self::assertSame(0, $this->score()($this->ana, self::home(), $table)->toArray()['team']);

        // the player acts as seat 2, then seats 3, 0 and 1 are the robots'
        $turn = $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));
        self::assertSame([1, 2, 3, 0, 1], $this->seatsThatActed());
        self::assertSame(2, $turn->view->toArray()['turn']);

        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));
        self::assertSame(Seat::Third, ($this->finished())($this->ana, self::home(), $table)->seat);
    }

    public function testThePlayersPartnerBidsByThePairsCardForTheWholeGameAndTheOpponentsByTheClassicOne(): void
    {
        $bold = ConventionCard::ready('bold');
        self::assertNotNull($bold);
        $table = $this->open($this->ana, seat: Seat::Third, card: $bold)->table->toString();
        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));
        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));

        // seat 0 faces the player: it alone is given the card, at every one of its turns
        self::assertNotSame([], $this->engine->cards);
        foreach ($this->engine->cards as [$seat, $card]) {
            self::assertSame(0 === $seat ? $bold->toArray() : null, $card, 'seat '.$seat);
        }
        self::assertContains(0, array_column($this->engine->cards, 0));
        self::assertContains(1, array_column($this->engine->cards, 0));
        // and the deal that was played is reviewed by the card it was bid by
        self::assertTrue($bold->equals(($this->finished())($this->ana, self::home(), $table)->card ?? ConventionCard::classic()));
    }

    public function testWithNoCardBroughtEveryRobotIsLeftToTheClassicOne(): void
    {
        $table = $this->open($this->ana)->table->toString();
        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));

        self::assertSame([null], array_values(array_unique(array_column($this->engine->cards, 1), \SORT_REGULAR)));
    }

    public function testEveryCallThatDrawsHasASeedOfItsOwnAndTheSameSeedGivesTheSameOnes(): void
    {
        $seed = Seed::fromHex(str_repeat('ab', 16));
        $first = $this->seedsOfTwoDeals($this->ana, $seed);

        // the player acted four times, robots in between: no seed served twice
        self::assertCount(\count($first), array_unique($first));
        self::assertGreaterThan(12, \count($first));
        self::assertNotContains($seed->toHex(), $first, 'The secret itself is never sent to the engine.');

        $this->engine = new TurnsEngine();
        self::assertSame($first, $this->seedsOfTwoDeals($this->ben, $seed));

        // and a table opened with no seed draws from a secret nobody chose
        $this->engine = new TurnsEngine();
        self::assertSame([], array_intersect($first, $this->seedsOfTwoDeals($this->ana, null)));
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

    public function testAnActionIsReadPlayedAndWrittenWhileNothingElseCanPlayAtTheTable(): void
    {
        $table = $this->open($this->ana)->table->toString();
        $this->tables->journal = [];

        $this->act()($this->ana, self::home(), $table, Action::bid(Bid::pass()));

        // Two cards sent at the same instant must not both be played from the same game: the second
        // reads the table only once the first has written it. ListedTables refuses a table held twice.
        self::assertSame(['find held', 'save held'], $this->tables->journal);

        // looking at a table holds nothing
        $this->tables->journal = [];
        $this->see()($this->ana, self::home(), $table);
        $this->score()($this->ana, self::home(), $table);
        self::assertSame(['find free', 'find free'], $this->tables->journal);
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
            self::assertSame('not now', $refused->getMessage());
        }
        self::assertSame($saved, $this->tables->saved);
        self::assertSame($before, $this->tables->kept[$turn->table->toString()]);
        // the table is let go of when a move is refused: the next one is not kept waiting
        $this->act()($this->ana, self::home(), $turn->table->toString(), Action::play(Card::fromCode('7S')));
        self::assertSame($saved + 1, $this->tables->saved);
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
            try {
                $own->alone($player, self::home(), $named, static fn (Table $table): Table => $table);
                self::fail('A table was handed to somebody it is not the table of, to play at.');
            } catch (NoSuchTable $none) {
                $refusals[] = $none->getMessage();
            }
        }
        self::assertCount(1, array_unique($refusals));
        self::assertSame($this->tables->kept[$table], $own($this->ana, self::home(), $table));

        foreach ([$this->see(), $this->moves(), $this->score(), $this->finished()] as $capability) {
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
        self::assertSame(Seat::First, $this->see()($this->ana, self::home(), $table)->seat());

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
        try {
            $this->act()($this->ana, self::home(), $table, Action::next());
            self::fail('Something was played at a table that has expired.');
        } catch (TableExpired) {
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
            fn (): mixed => $this->moves()($this->ana, self::home(), $table),
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

    public function testTablesAreCountedApartFromTheCallsAndTheOneTooManyIsNotOpened(): void
    {
        $this->pace = new CountingPace(tablesLeft: 2);
        $this->open($this->ana);
        $table = $this->open($this->ana)->table->toString();

        try {
            $this->open($this->ana);
            self::fail('A third table was opened.');
        } catch (PlayedTooFast $tooMany) {
            self::assertSame(600, $tooMany->retryAfter);
        }
        self::assertCount(2, $this->tables->kept);
        // the tables that are open are played at as before
        self::assertSame(Seat::First, $this->see()($this->ana, self::home(), $table)->seat());
    }

    public function testTheScoreIsTheCountAndNothingOfTheCards(): void
    {
        $table = $this->open($this->ana, seat: Seat::Second)->table->toString();

        $score = $this->score()($this->ana, self::home(), $table)->toArray();

        self::assertSame(['table', 'team', 'phase', 'round', 'scoring', 'scores'], array_keys($score));
        self::assertSame([$table, 1, [0, 0]], [$score['table'], $score['team'], $score['scores']]);
    }

    /** @return list<string> the seeds of every call that drew, over a deal, the next one asked for, and the player's first word in it */
    private function seedsOfTwoDeals(PlayerId $player, ?Seed $seed): array
    {
        $table = $this->open($player, seed: $seed)->table->toString();
        $this->act()($player, self::home(), $table, Action::bid(Bid::pass()));
        $this->act()($player, self::home(), $table, Action::next());
        $this->act()($player, self::home(), $table, Action::bid(Bid::pass()));
        $this->act()($player, self::home(), $table, Action::next());

        return array_column($this->engine->drawn, 3);
    }

    /** @return list<int> */
    private function seatsThatActed(): array
    {
        return array_column(array_filter($this->engine->drawn, static fn (array $call): bool => 'apply' === $call[0]), 1);
    }

    private function open(PlayerId $player, Level $level = Level::Easy, Seat $seat = Seat::First, ?Seed $seed = null, ?ConventionCard $card = null): Turn
    {
        return (new OpenTable($this->engine, $this->robots(), $this->pace, new ClockNow($this->clock)))($player, self::home(), $level, $seat, $seed, $card);
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

    private function moves(): ListMoves
    {
        return new ListMoves($this->own(), $this->engine);
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
