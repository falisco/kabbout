<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Phase;
use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Infrastructure\Bridge\BridgeDealAnalysis;
use Kabbout\Game\Infrastructure\Bridge\BridgeEngine;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Game\Infrastructure\Bridge\Transport\HttpBridgeTransport;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The same contract, against the bridge itself. It needs one running, at the address of BRIDGE_URL:
 * `npm run check:php` starts it.
 */
#[Group('integration')]
final class BridgeEngineIntegrationTest extends EngineContractTestCase
{
    protected function engine(): Engine
    {
        return new BridgeEngine(self::transport());
    }

    protected function analysis(): DealAnalysis
    {
        return new BridgeDealAnalysis(self::transport());
    }

    private static function transport(): HttpBridgeTransport
    {
        $url = getenv('BRIDGE_URL');
        if (false === $url || '' === $url) {
            self::markTestSkipped('No bridge to ask: BRIDGE_URL is not set.');
        }

        return new HttpBridgeTransport(HttpClient::create(), $url);
    }

    public function testADealPlayedByRobotsNeverShowsASeatACardItMayNotKnow(): void
    {
        $engine = $this->engine();
        $secret = Seed::fromHex(str_repeat('2a', 16));
        $state = $engine->newGame($secret->derive('deal'), Scoring::Tunisian, dealer: Seat::Third)->state;
        $dealt = $state;
        $moves = 0;
        while (Phase::RoundEnd !== $state->phase()) {
            $this->assertEverySeatSeesOnlyItsOwn($engine, $state);
            $move = $engine->robotMove($state, Level::Expert, $secret->derive('robot '.$moves));
            self::assertSame($state->turn(), $move->seat);
            $step = $engine->apply($state, $move->seat, $move->action, $secret->derive('move '.$moves));
            // four passes deal again: the deal to replay is the one that gets played
            if ([] !== array_filter($step->events, static fn (array $event): bool => 'dealt' === ($event['type'] ?? null))) {
                $dealt = $step->state;
            }
            $state = $step->state;
            ++$moves;
        }
        self::assertGreaterThanOrEqual(35, $moves);
        self::assertTrue($engine->legalMoves($state, Seat::Fourth)->next);

        $played = $state->toEngineArray();
        $record = DealRecord::fromArray(['scoring' => 'tunisian', 'dealer' => $played['dealer'], 'bids' => $played['bids'], 'tricks' => self::plays($played['tricks'])]);
        $replayed = $engine->replay($record);
        self::assertEquals($played['result'], $replayed->result);
        self::assertSame(self::sortedHands($dealt->toEngineArray()['hands']), self::sortedHands($replayed->hands));
    }

    public function testASeedUsedTwiceDealsTheSameCardsAndADerivedOneDoesNot(): void
    {
        $engine = $this->engine();
        $secret = Seed::fromHex(str_repeat('7b', 16));
        $state = $engine->newGame($secret->derive('deal 1'))->state;
        $moves = 0;
        while (Phase::RoundEnd !== $state->phase()) {
            $move = $engine->robotMove($state, Level::Easy, $secret->derive('robot '.$moves));
            $state = $engine->apply($state, $move->seat, $move->action, $secret->derive('move '.$moves++))->state;
        }
        $hands = static fn (Seed $seed): mixed => $engine->apply($state, Seat::First, Action::next(), $seed)->state->toEngineArray()['hands'];

        self::assertSame($hands($secret->derive('deal 2')), $hands($secret->derive('deal 2')));
        self::assertNotSame($hands($secret->derive('deal 2')), $hands($secret->derive('deal 3')));
    }

    /** Wherever a card is written in a view, under any name, the seat holds it or the table has seen it. */
    private function assertEverySeatSeesOnlyItsOwn(Engine $engine, GameState $state): void
    {
        $full = $state->toEngineArray();
        $hands = Json::list($full['hands'], 'hands');
        $played = self::cardsIn(['tricks' => $full['tricks'], 'trick' => $full['trick']]);
        foreach (Seat::cases() as $seat) {
            $view = $engine->view($state, $seat);
            $own = Json::strings($hands[$seat->value], 'cards');
            sort($own);
            $shown = $view->hand();
            sort($shown);
            self::assertSame($own, $shown);
            self::assertSame([], array_values(array_diff(self::cardsIn($view->toArray()), $own, $played)), \sprintf('Seat %d is shown cards it may not know.', $seat->value));
            self::assertSame($seat !== $state->turn(), $engine->legalMoves($state, $seat)->isEmpty());
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string> every card written anywhere in it
     */
    private static function cardsIn(array $data): array
    {
        preg_match_all('/"([789TJQKA][SHDC])"/', json_encode($data, \JSON_THROW_ON_ERROR), $found);

        return array_values(array_unique($found[1]));
    }

    /** @return list<mixed> */
    private static function plays(mixed $tricks): array
    {
        return array_map(static fn (array $trick): mixed => $trick['plays'] ?? null, Json::objects($tricks, 'tricks'));
    }

    /** @return list<list<string>> */
    private static function sortedHands(mixed $hands): array
    {
        return array_map(static function (mixed $hand): array {
            $cards = Json::strings($hand, 'cards');
            sort($cards);

            return $cards;
        }, Json::list($hands, 'hands'));
    }
}
