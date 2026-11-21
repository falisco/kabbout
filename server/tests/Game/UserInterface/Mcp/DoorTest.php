<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\MoveRefused;
use Kabbout\Game\Application\Table\NoFinishedDeal;
use Kabbout\Game\Application\Table\NoSuchTable;
use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\TableExpired;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Exception\InvalidBid;
use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Game\UserInterface\Mcp\Door;
use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** Around every MCP tool: who is asking and from where, an answer a model reads cheaply, a refusal it can act on. */
final class DoorTest extends TestCase
{
    public function testTheCapabilityIsAskedForWhoeverIsAskingFromWhereTheRequestComes(): void
    {
        $ana = self::ana();
        $requests = new RequestStack();
        $requests->push(Request::create('/mcp', server: ['REMOTE_ADDR' => '203.0.113.9']));

        $answer = self::door($ana, $requests)(static fn (PlayerId $player, NetworkAddress $from): array => ['who' => $player->toString(), 'from' => $from->key]);

        self::assertSame(['who' => $ana->id->toString(), 'from' => 'address:203.0.113.9'], $answer->structuredContent);
        // a process with no request has no address
        self::assertSame(['from' => 'address:unknown'], self::door($ana, new RequestStack())(static fn (PlayerId $player, NetworkAddress $from): array => ['from' => $from->key])->structuredContent);
    }

    public function testAnAnswerIsDataAndTheSameDataOnOneLine(): void
    {
        $answer = self::door(self::ana(), new RequestStack())(static fn (): array => ['view' => ['hand' => ['7S', 'JH'], 'said' => 'à pique / سباتة'], 'legal' => ['next' => false]]);

        self::assertFalse($answer->isError);
        self::assertCount(1, $answer->content);
        self::assertInstanceOf(TextContent::class, $answer->content[0]);
        // no line per value, no escaped letter, no escaped slash: this text is what a model is handed, on every call
        self::assertSame('{"view":{"hand":["7S","JH"],"said":"à pique / سباتة"},"legal":{"next":false}}', $answer->content[0]->text);
        self::assertSame(json_decode($answer->content[0]->text, true), $answer->structuredContent);
    }

    public function testNobodyIsAnsweredThatNobodyIsAsking(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Nobody is asking');

        self::door(null, new RequestStack())(static fn (): array => self::fail('The capability was asked for nobody.'));
    }

    #[DataProvider('refusals')]
    public function testWhatIsRefusedIsSaidInWordsTheModelIsShown(\Throwable $refusal, string $said): void
    {
        try {
            self::door(self::ana(), new RequestStack())(static fn (): array => throw $refusal);
            self::fail('A refusal went through as an answer.');
        } catch (ToolCallException $shown) {
            self::assertSame($said, $shown->getMessage());
            self::assertSame($refusal, $shown->getPrevious());
        }
    }

    /** @return iterable<string, array{\Throwable, string}> */
    public static function refusals(): iterable
    {
        $down = 'The game cannot be reached right now. Nothing has changed; try again shortly.';
        yield 'a move the engine refuses, with the moves it allows' => [
            new MoveRefused('illegal card 9S by seat 0', new LegalMoves([], [Card::fromCode('7H'), Card::fromCode('AH')], false)),
            'Refused: illegal card 9S by seat 0. Nothing has changed. What you may do now: {"bids":[],"cards":["7H","AH"],"next":false}',
        ];
        yield 'no such table, and what to do about it' => [new NoSuchTable(), 'There is no such table. Open one with new_game.'];
        yield 'a table that has expired' => [new TableExpired(), 'This table has expired: nothing was played on it for 3 hours. Open one with new_game.'];
        yield 'no deal to review yet' => [new NoFinishedDeal(), 'No deal has been played to its end at this table yet. A deal is reviewed once its eight tricks are played.'];
        yield 'too fast' => [new PlayedTooFast(12), 'Too many calls from one place. Try again in 12 seconds.'];
        yield 'a raise with no suit' => [new InvalidBid('A bid of type "bid" names its value and its trump suit.'), 'A bid of type "bid" names its value and its trump suit.'];
        yield 'the engine says no to a review' => [new EngineRefused('the seat did not hold QH.'), 'Refused: the seat did not hold QH.'];
        yield 'too many new players' => [new EnteredTooOften(30), 'Too many new players right now. Try again in a minute.'];
        yield 'no name left' => [new NoNameFree(), 'Too many new players right now. Try again in a minute.'];
        yield 'no engine' => [new EngineUnavailable('down'), $down];
        yield 'no tables' => [new TablesUnavailable(), $down];
        yield 'no players' => [new PlayersUnavailable(), $down];
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function mistakes(): iterable
    {
        yield 'a bug' => [new \LogicException('a bug')];
        // the largest family of mistakes: a value of ours, of Symfony or of the SDK that refused to be built
        yield 'a value that is none' => [new \InvalidArgumentException('Not a game state: no phase.')];
        yield 'a wrong type' => [new \TypeError('a wrong type')];
    }

    #[DataProvider('mistakes')]
    public function testAMistakeOfTheProgramIsNotDressedAsARefusal(\Throwable $mistake): void
    {
        try {
            self::door(self::ana(), new RequestStack())(static fn (): array => throw $mistake);
            self::fail('A mistake went through as an answer.');
        } catch (\Throwable $thrown) {
            self::assertSame($mistake, $thrown);
        }
    }

    private static function ana(): Player
    {
        return Player::guest(PlayerId::random(), PlayerName::fromString('C0000001'));
    }

    private static function door(?Player $player, RequestStack $requests): Door
    {
        return new Door(new class($player) implements CurrentPlayer {
            public function __construct(private readonly ?Player $who)
            {
            }

            public function player(): ?Player
            {
                return $this->who;
            }
        }, $requests);
    }
}
