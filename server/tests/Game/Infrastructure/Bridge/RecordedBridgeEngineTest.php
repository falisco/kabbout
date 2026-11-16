<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Infrastructure\Bridge\BridgeDealAnalysis;
use Kabbout\Game\Infrastructure\Bridge\BridgeEngine;
use Kabbout\Tests\Support\RecordedBridgeTransport;

/**
 * The engine with no bridge running, answered from the recorded contract: what this proves is that
 * BridgeEngine writes each question as the bridge reads it and reads each answer as the bridge writes it.
 * That the answers are the engine's is proved where they are recorded, and by the integration test.
 */
final class RecordedBridgeEngineTest extends EngineContractTestCase
{
    protected function engine(): Engine
    {
        return new BridgeEngine(RecordedBridgeTransport::fromContractFile(self::CASES));
    }

    protected function analysis(): DealAnalysis
    {
        return new BridgeDealAnalysis(RecordedBridgeTransport::fromContractFile(self::CASES));
    }

    public function testAQuestionItWasNeverGivenFailsLoudly(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('never given an answer to this call of /games');
        (new BridgeEngine(new RecordedBridgeTransport()))->newGame(Seed::fromHex(str_repeat('1', 32)));
    }

    public function testAQuestionIsTheSameWhateverTheOrderOfItsFields(): void
    {
        $seed = Seed::fromHex(str_repeat('5', 32));
        $transport = new RecordedBridgeTransport();
        $transport->record('/games', ['scoring' => 'tunisian', 'seed' => $seed->toHex()], 200, ['state' => ['turn' => 1, 'phase' => 'bidding'], 'events' => []]);

        self::assertSame(Seat::Second, (new BridgeEngine($transport))->newGame($seed, Scoring::Tunisian)->state->turn());
    }

    public function testItCanBeTaughtARefusalOrAFailure(): void
    {
        $state = new GameState(['phase' => 'bidding', 'turn' => 0]);
        $transport = new RecordedBridgeTransport();
        $transport->record('/games/view', ['state' => $state->toEngineArray(), 'seat' => 0], 422, ['error' => ['code' => 'refused', 'message' => 'no']]);
        $transport->record('/games/view', ['state' => $state->toEngineArray(), 'seat' => 1], 500, ['error' => ['code' => 'internal', 'message' => 'the bridge failed']]);
        $engine = new BridgeEngine($transport);

        try {
            $engine->view($state, Seat::First);
            self::fail('A recorded refusal should be thrown.');
        } catch (EngineRefused $refused) {
            self::assertSame('no', $refused->getMessage());
        }
        $this->expectException(EngineUnavailable::class);
        $this->expectExceptionMessage('The bridge answered 500 to /games/view: the bridge failed');
        $engine->view($state, Seat::Second);
    }

    public function testAMissingContractFileIsSaid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        @RecordedBridgeTransport::fromContractFile(__DIR__.'/nowhere.json');
    }
}
