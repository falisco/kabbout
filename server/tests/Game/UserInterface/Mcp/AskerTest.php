<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\NoSuchTable;
use Kabbout\Game\Application\Table\TablesUnavailable;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\UserInterface\Mcp\Asker;
use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** Around every MCP tool: who is asking and from where, and a refusal the client's model can read. */
final class AskerTest extends TestCase
{
    public function testTheCapabilityIsAskedForWhoeverIsAskingFromWhereTheRequestComes(): void
    {
        $ana = Player::guest(PlayerId::random(), PlayerName::fromString('C0000001'));
        $requests = new RequestStack();
        $requests->push(Request::create('/mcp', server: ['REMOTE_ADDR' => '203.0.113.9']));

        $answer = self::asker($ana, $requests)(static fn (Player $player, NetworkAddress $from): array => ['who' => $player->name->toString(), 'from' => $from->key]);

        self::assertSame(['who' => 'C0000001', 'from' => 'address:203.0.113.9'], $answer);
        // a process with no request has no address
        self::assertSame('address:unknown', self::asker($ana, new RequestStack())(static fn (Player $player, NetworkAddress $from): array => ['from' => $from->key])['from']);
    }

    public function testNobodyIsAnsweredThatNobodyIsAsking(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Nobody is asking');

        self::asker(null, new RequestStack())(static fn (): array => self::fail('The capability was asked for nobody.'));
    }

    #[DataProvider('refusals')]
    public function testWhatIsRefusedIsSaidInWordsTheModelIsShown(\Throwable $refusal, string $said): void
    {
        $ana = Player::guest(PlayerId::random(), PlayerName::fromString('C0000001'));

        try {
            self::asker($ana, new RequestStack())(static fn (): array => throw $refusal);
            self::fail('A refusal went through as an answer.');
        } catch (ToolCallException $shown) {
            self::assertStringContainsString($said, $shown->getMessage());
            self::assertSame($refusal, $shown->getPrevious());
        }
    }

    /** @return iterable<string, array{\Throwable, string}> */
    public static function refusals(): iterable
    {
        yield 'the table says no' => [new NoSuchTable(), 'There is no such table'];
        yield 'the engine says no' => [new EngineRefused('illegal card 9S by seat 0'), 'Refused: illegal card 9S by seat 0'];
        yield 'a value that is none' => [new \InvalidArgumentException('"XX" is not a card.'), '"XX" is not a card.'];
        yield 'too many new players' => [new EnteredTooOften(30), 'Too many new players'];
        yield 'no engine' => [new EngineUnavailable('down'), 'cannot be reached right now'];
        yield 'no tables' => [new TablesUnavailable(), 'cannot be reached right now'];
        yield 'no players' => [new PlayersUnavailable(), 'cannot be reached right now'];
    }

    public function testAMistakeOfTheProgramIsNotDressedAsARefusal(): void
    {
        $ana = Player::guest(PlayerId::random(), PlayerName::fromString('C0000001'));

        $this->expectException(\LogicException::class);
        self::asker($ana, new RequestStack())(static fn (): array => throw new \LogicException('a bug'));
    }

    private static function asker(?Player $player, RequestStack $requests): Asker
    {
        return new Asker(new class($player) implements CurrentPlayer {
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
