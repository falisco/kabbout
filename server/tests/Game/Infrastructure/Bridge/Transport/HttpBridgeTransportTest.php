<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Bridge\Transport;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineException;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Suit;
use Kabbout\Game\Infrastructure\Bridge\BridgeEngine;
use Kabbout\Game\Infrastructure\Bridge\Transport\HttpBridgeTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The engine over HTTP, facing a bridge that answers what the test decides. */
final class HttpBridgeTransportTest extends TestCase
{
    private const array STATE = ['phase' => 'bidding', 'turn' => 1, 'hands' => [[], [], [], []], 'bids' => [], 'tricks' => []];

    private static function engine(MockHttpClient $client, string $url = 'http://bridge'): Engine
    {
        return new BridgeEngine(new HttpBridgeTransport($client, $url));
    }

    private static function seed(): Seed
    {
        return Seed::fromHex(str_repeat('0c', 16));
    }

    public function testACallIsAPostOfJsonToTheBridge(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [$method, $url, $options['body'] ?? null];

            return new JsonMockResponse(['state' => self::STATE, 'events' => [['type' => 'bid', 'record' => ['type' => 'bid', 'value' => 90, 'suit' => 'H', 'seat' => 1]]]]);
        });

        $step = self::engine($client, 'http://bridge:8787/')->apply(new GameState(self::STATE), Seat::Second, Action::bid(Bid::raise(90, Suit::Hearts)), self::seed());

        self::assertSame('POST', $seen[0]);
        self::assertSame('http://bridge:8787/games/apply', $seen[1]);
        self::assertIsString($seen[2]);
        self::assertEquals(
            ['state' => self::STATE, 'seat' => 1, 'action' => ['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 90, 'suit' => 'H']], 'seed' => str_repeat('0c', 16)],
            json_decode($seen[2], true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Seat::Second, $step->state->turn());
        self::assertCount(1, $step->events);
    }

    public function testARefusalOfTheEngineIsThrownWithItsWordsAndItsPlace(): void
    {
        $client = new MockHttpClient(new JsonMockResponse(['error' => ['code' => 'refused', 'message' => 'illegal card 8D by seat 0', 'at' => ['trick' => 1, 'seat' => 0]]], ['http_code' => 422]));

        try {
            self::engine($client)->apply(new GameState(self::STATE), Seat::First, Action::play(Card::fromCode('8D')), self::seed());
            self::fail('The refusal should be thrown.');
        } catch (EngineRefused $refused) {
            self::assertSame('illegal card 8D by seat 0', $refused->getMessage());
            self::assertSame(['trick' => 1, 'seat' => 0], $refused->at);
        }
    }

    /** @return iterable<string, array{MockResponse}> */
    public static function failures(): iterable
    {
        yield 'the bridge fails' => [new JsonMockResponse(['error' => ['code' => 'internal', 'message' => 'the bridge failed']], ['http_code' => 500])];
        yield 'the bridge does not understand us' => [new JsonMockResponse(['error' => ['code' => 'bad_request', 'message' => 'seed: an integer expected']], ['http_code' => 400])];
        yield 'an error with no message' => [new JsonMockResponse(['error' => []], ['http_code' => 503])];
        yield 'an error that is not one' => [new JsonMockResponse(['oops'], ['http_code' => 502])];
        yield 'something that is not JSON' => [new MockResponse('<html>', ['http_code' => 200])];
        yield 'JSON that is not an answer' => [new JsonMockResponse([1, 2])];
        yield 'an answer with no state' => [new JsonMockResponse(['events' => []])];
        yield 'a state that is not one' => [new JsonMockResponse(['state' => ['phase' => 'dancing', 'turn' => 0], 'events' => []])];
        yield 'events that are not a list' => [new JsonMockResponse(['state' => self::STATE, 'events' => 'none'])];
        yield 'nobody answers' => [new MockResponse([new TransportException('connection refused')])];
    }

    #[DataProvider('failures')]
    public function testAnythingElseIsOurOwnFailure(MockResponse $response): void
    {
        $engine = self::engine(new MockHttpClient($response));

        $this->expectException(EngineUnavailable::class);
        $engine->newGame(self::seed());
    }

    public function testAViewThatHoldsTheFourHandsIsNeverPassedOn(): void
    {
        $engine = self::engine(new MockHttpClient(new JsonMockResponse(['view' => ['seat' => 0, 'hand' => [], 'hands' => [[], [], [], []]]])));

        $this->expectException(EngineUnavailable::class);
        $this->expectExceptionMessage('holds "hands"');
        $engine->view(new GameState(self::STATE), Seat::First);
    }

    public function testARobotMoveWithAnUnknownSeatIsOurOwnFailure(): void
    {
        $engine = self::engine(new MockHttpClient(new JsonMockResponse(['seat' => 7, 'action' => ['kind' => 'next']])));

        $this->expectException(EngineUnavailable::class);
        $engine->robotMove(new GameState(self::STATE), Level::Easy, self::seed());
    }

    public function testLegalMovesWithNoWordOnTheNextDealAreNotHalfRead(): void
    {
        $engine = self::engine(new MockHttpClient(new JsonMockResponse(['bids' => [], 'cards' => []])));

        $this->expectException(EngineUnavailable::class);
        $engine->legalMoves(new GameState(self::STATE), Seat::First);
    }

    public function testEveryFailureOfTheEngineIsOneKindOfException(): void
    {
        $engine = self::engine(new MockHttpClient(new JsonMockResponse(['error' => ['code' => 'refused', 'message' => 'no']], ['http_code' => 422])));

        $this->expectException(EngineException::class);
        $engine->newGame(self::seed());
    }

    public function testAFailureIsSaidWithItsStatus(): void
    {
        $engine = self::engine(new MockHttpClient(new JsonMockResponse(['error' => ['code' => 'bad_request', 'message' => 'state: not a game state']], ['http_code' => 400])));

        $this->expectException(EngineUnavailable::class);
        $this->expectExceptionMessage('The bridge answered 400 to /games: state: not a game state');
        $engine->newGame(self::seed());
    }
}
