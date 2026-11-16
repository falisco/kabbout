<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Domain;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\InvalidDealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\Phase;
use Kabbout\Game\Domain\RobotMove;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Step;
use Kabbout\Game\Domain\Suit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testSeatsGoRoundTheTableAndFaceTheirPartner(): void
    {
        self::assertSame(Seat::First, Seat::Fourth->next());
        self::assertSame(Seat::Third, Seat::Second->next());
        self::assertSame(Seat::Fourth, Seat::Second->partner());
        self::assertSame([0, 1, 0, 1], array_map(static fn (Seat $seat): int => $seat->team(), Seat::cases()));
    }

    public function testActionsAreWrittenAsTheEngineReadsThem(): void
    {
        self::assertSame(['kind' => 'bid', 'bid' => ['type' => 'pass']], Action::pass()->toEngineArray());
        self::assertSame(['kind' => 'bid', 'bid' => ['type' => 'contre']], Action::contre()->toEngineArray());
        self::assertSame(['kind' => 'bid', 'bid' => ['type' => 'surcontre']], Action::surcontre()->toEngineArray());
        self::assertSame(['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 110, 'suit' => 'C']], Action::bid(110, Suit::Clubs)->toEngineArray());
        self::assertSame(['kind' => 'play', 'card' => 'TH'], Action::play(Card::fromCode('TH'))->toEngineArray());
        self::assertSame(['kind' => 'next'], Action::next()->toEngineArray());
        self::assertSame(['kind' => 'bid', 'bid' => ['type' => 'pass'], 'why' => 'nothing to say'], Action::fromEngine(['kind' => 'bid', 'bid' => ['type' => 'pass'], 'why' => 'nothing to say'])->toEngineArray());
    }

    public function testACardIsARankThenASuit(): void
    {
        $card = Card::fromCode('TH');

        self::assertSame('TH', $card->code());
        self::assertSame(Suit::Hearts, $card->suit());
        self::assertSame(['bids' => [], 'cards' => ['TH'], 'next' => false], (new LegalMoves([], [$card], false))->toArray());
    }

    /** @return iterable<string, array{string}> */
    public static function notCards(): iterable
    {
        yield 'nothing' => [''];
        yield 'a rank alone' => ['J'];
        yield 'an unknown rank' => ['2S'];
        yield 'an unknown suit' => ['JX'];
        yield 'the suit first' => ['SJ'];
        yield 'too long' => ['10S'];
    }

    #[DataProvider('notCards')]
    public function testACodeThatNamesNoCardIsRefused(string $code): void
    {
        self::assertNull(Card::tryFromCode($code));
        $this->expectException(\InvalidArgumentException::class);
        Card::fromCode($code);
    }

    public function testAGameStateSaysWhoseTurnItIsAndKeepsTheRestUntouched(): void
    {
        $data = ['phase' => 'playing', 'turn' => 3, 'anything' => ['the engine' => 'wrote']];
        $state = new GameState($data);

        self::assertSame(Phase::Playing, $state->phase());
        self::assertSame(Seat::Fourth, $state->turn());
        self::assertSame($data, $state->toEngineArray());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function notStates(): iterable
    {
        yield 'nothing' => [[]];
        yield 'an unknown phase' => [['phase' => 'dancing', 'turn' => 0]];
        yield 'a phase that is not a word' => [['phase' => 1, 'turn' => 0]];
        yield 'no seat to act' => [['phase' => 'bidding']];
        yield 'a fifth seat' => [['phase' => 'bidding', 'turn' => 4]];
        yield 'a seat that is not a number' => [['phase' => 'bidding', 'turn' => '0']];
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('notStates')]
    public function testWhatIsNotAGameStateIsRefused(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameState($data);
    }

    public function testASeatViewGivesTheSeatAndItsCards(): void
    {
        $data = ['seat' => 2, 'hand' => ['JS', '9S', 'AH'], 'bids' => [['type' => 'pass', 'seat' => 1]], 'handSizes' => [8, 8, 3, 8]];
        $view = new SeatView($data);

        self::assertSame(Seat::Third, $view->seat());
        self::assertSame(['JS', '9S', 'AH'], $view->hand());
        self::assertSame($data, $view->toArray());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function notViews(): iterable
    {
        yield 'no seat' => [['hand' => []], 'no seat'];
        yield 'a fifth seat' => [['seat' => 4, 'hand' => []], 'no seat'];
        yield 'the full state' => [['seat' => 0, 'hand' => [], 'hands' => [[], [], [], []]], 'holds "hands"'];
        yield 'no hand' => [['seat' => 0], 'no hand'];
        yield 'a hand that is not cards' => [['seat' => 0, 'hand' => ['7S', 8]], 'no hand'];
        yield 'something stored with the game' => [['seat' => 0, 'hand' => [], 'tokens' => ['secret']], 'holds "tokens"'];
        yield 'a robot\'s reason' => [['seat' => 0, 'hand' => [], 'bids' => [['type' => 'pass', 'seat' => 1, 'why' => 'no trump jack']]], 'reason'];
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('notViews')]
    public function testASeatViewRefusesWhatASeatMayNotKnow(array $data, string $why): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($why);
        new SeatView($data);
    }

    public function testLegalMovesAreEmptyOutOfTurn(): void
    {
        self::assertTrue((new LegalMoves([], [], false))->isEmpty());
        self::assertFalse((new LegalMoves([['type' => 'pass']], [], false))->isEmpty());
        self::assertFalse((new LegalMoves([], [Card::fromCode('7S')], false))->isEmpty());
        self::assertFalse((new LegalMoves([], [], true))->isEmpty());
    }

    public function testARobotsReasonGoesToTheEngineAndNotToTheTable(): void
    {
        $bid = Action::fromEngine(['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 80, 'suit' => 'S'], 'why' => 'jack and nine of spades']);
        $move = new RobotMove(Seat::Second, $bid);

        self::assertSame('jack and nine of spades', $move->toEngineArray()['action']['why'] ?? null);
        self::assertSame(['seat' => 1, 'action' => ['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 80, 'suit' => 'S']]], $move->toPublicArray());
        self::assertSame(Action::pass()->toEngineArray(), Action::pass()->withoutReason()->toEngineArray());

        $step = new Step(new GameState(['phase' => 'bidding', 'turn' => 2]), [['type' => 'bid']]);
        self::assertSame(['state' => ['phase' => 'bidding', 'turn' => 2], 'events' => [['type' => 'bid']]], $step->toEngineArray());
    }

    public function testASeedIsLongEnoughNotToBeGuessedAndDerivesOnePerUse(): void
    {
        $secret = Seed::random();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret->toHex());
        self::assertNotSame($secret->toHex(), Seed::random()->toHex());
        self::assertSame($secret->derive('move 1')->toHex(), $secret->derive('move 1')->toHex());
        self::assertNotSame($secret->derive('move 1')->toHex(), $secret->derive('move 2')->toHex());
        self::assertNotSame($secret->toHex(), $secret->derive('move 1')->toHex());
        self::assertSame(str_repeat('0a', 16), Seed::fromHex(str_repeat('0a', 16))->toHex());
    }

    /** @return iterable<string, array{string}> */
    public static function notSeeds(): iterable
    {
        yield 'thirty-two bits, which one hand is enough to find again' => ['0000002a'];
        yield 'one digit short' => [str_repeat('f', 31)];
        yield 'too long' => [str_repeat('f', 65)];
        yield 'upper case' => [str_repeat('F', 32)];
        yield 'not hexadecimal' => [str_repeat('g', 32)];
        yield 'a line break after it' => [str_repeat('f', 32)."\n"];
    }

    #[DataProvider('notSeeds')]
    public function testWhatIsNotASeedIsRefused(string $hex): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Seed::fromHex($hex);
    }

    /** @return array{scoring: string, dealer: int, bids: list<array<string, mixed>>, tricks: list<list<array<string, mixed>>>} */
    private static function record(): array
    {
        $trick = [['seat' => 1, 'card' => '7S'], ['seat' => 2, 'card' => '8S'], ['seat' => 3, 'card' => '9S'], ['seat' => 0, 'card' => 'JS']];

        return [
            'scoring' => 'tunisian',
            'dealer' => 0,
            'bids' => [['seat' => 1, 'type' => 'bid', 'value' => 80, 'suit' => 'S'], ['seat' => 2, 'type' => 'pass'], ['seat' => 3, 'type' => 'contre'], ['seat' => 0, 'type' => 'surcontre']],
            'tricks' => array_fill(0, 8, $trick),
        ];
    }

    public function testADealRecordKeepsOnlyTheFieldsARecordIsMadeOf(): void
    {
        $sent = self::record();
        $sent['cheat'] = true;
        $sent['bids'][0]['why'] = str_repeat('x', 300_000);
        $sent['bids'][1]['value'] = 500;
        $sent['tricks'][0][0]['hands'] = [['AS']];

        self::assertSame(self::record(), DealRecord::fromArray($sent)->toArray());
    }

    /** @return iterable<string, array{string, mixed}> the field of a sound record that is damaged, and what it is replaced with */
    public static function notRecords(): iterable
    {
        yield 'no count' => ['scoring', null];
        yield 'an unknown count' => ['scoring', 'bridge'];
        yield 'a fifth seat as dealer' => ['dealer', 4];
        yield 'a dealer that is not a number' => ['dealer', '0'];
        yield 'no auction' => ['bids', 'pass'];
        yield 'an auction that is not a list' => ['bids', ['first' => ['seat' => 0, 'type' => 'pass']]];
        yield 'an endless auction' => ['bids', array_fill(0, 65, ['seat' => 0, 'type' => 'pass'])];
        yield 'a bid that is not one' => ['bids', ['pass']];
        yield 'a bid with no seat' => ['bids', [['type' => 'pass']]];
        yield 'a bid of an unknown kind' => ['bids', [['seat' => 0, 'type' => 'double']]];
        yield 'a raise with no value' => ['bids', [['seat' => 0, 'type' => 'bid', 'suit' => 'S']]];
        yield 'a raise with a suit that is not one letter' => ['bids', [['seat' => 0, 'type' => 'bid', 'value' => 80, 'suit' => 'spades']]];
        yield 'seven tricks' => ['tricks', array_fill(0, 7, self::record()['tricks'][0])];
        yield 'tricks that are not a list' => ['tricks', 'none'];
        yield 'a trick of three plays' => ['tricks', array_fill(0, 8, [['seat' => 0, 'card' => '7S']])];
        yield 'a trick that is not a list' => ['tricks', array_fill(0, 8, 'trick')];
        yield 'a play with no card' => ['tricks', array_fill(0, 8, array_fill(0, 4, ['seat' => 0]))];
        yield 'a card that is not two letters' => ['tricks', array_fill(0, 8, array_fill(0, 4, ['seat' => 0, 'card' => 'ace of spades']))];
        yield 'a play that is not one' => ['tricks', array_fill(0, 8, array_fill(0, 4, '7S'))];
    }

    #[DataProvider('notRecords')]
    public function testWhatDoesNotHaveTheShapeOfADealRecordIsTheSendersMistake(string $field, mixed $damaged): void
    {
        $this->expectException(InvalidDealRecord::class);
        DealRecord::fromArray([...self::record(), $field => $damaged]);
    }
}
