<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\RobotMove;
use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Infrastructure\Bridge\Mapping\ActionMapper;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Tests\Support\Canonical;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The contract every Engine keeps: the calls of bridge/contract/cases.json, recorded from the bridge
 * (whose own tests check the file against what it answers), each made through the typed interface.
 * An accepted call gives the recorded answer, to the type; a refused one throws EngineRefused with the engine's words.
 */
abstract class EngineContractTestCase extends TestCase
{
    public const string CASES = __DIR__.'/../../../../../bridge/contract/cases.json';

    abstract protected function engine(): Engine;

    abstract protected function analysis(): DealAnalysis;

    /** @return iterable<string, array{string, array<string, mixed>, int, array<string, mixed>}> */
    public static function calls(): iterable
    {
        $cases = json_decode((string) file_get_contents(self::CASES), true, flags: \JSON_THROW_ON_ERROR);
        foreach (Json::objects($cases, 'calls') as $case) {
            self::assertIsString($case['name']);
            self::assertIsString($case['path']);
            self::assertIsInt($case['status']);
            // a call is refused when its name says so, and only then
            self::assertSame(str_starts_with($case['name'], 'refused:') ? 422 : 200, $case['status'], $case['name']);
            yield $case['name'] => [$case['path'], Json::object($case['request'], 'request'), $case['status'], Json::object($case['response'], 'response')];
        }
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     */
    #[DataProvider('calls')]
    public function testACallGetsTheAnswerOfTheBridge(string $path, array $request, int $status, array $response): void
    {
        if (200 === $status) {
            // the same values of the same types, whatever the order of the fields
            self::assertSame(Canonical::sorted($response), Canonical::sorted($this->ask($path, $request)));

            return;
        }
        self::assertSame(422, $status, 'The contract only holds accepted and refused calls.');
        $error = Json::object($response['error'], 'error');
        try {
            $this->ask($path, $request);
            self::fail('The engine should have refused.');
        } catch (EngineRefused $refused) {
            self::assertSame($error['message'], $refused->getMessage());
            self::assertSame($error['at'] ?? null, $refused->at);
        }
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array<string, mixed>
     */
    private function ask(string $path, array $r): array
    {
        $engine = $this->engine();
        $state = static fn (): GameState => new GameState(Json::object($r['state'], 'state'));
        $seat = static fn (string $field): Seat => Seat::from(Json::int($r[$field], $field));
        $string = static fn (string $field): string => \is_string($r[$field]) ? $r[$field] : self::fail($field);
        $seed = static fn (): Seed => Seed::fromHex($string('seed'));
        $record = static fn (): DealRecord => DealRecord::fromArray(Json::object($r['record'], 'record'));
        $trick = static fn (): TrickNumber => TrickNumber::of(Json::int($r['trick'], 'trick'));
        if (str_starts_with($path, '/analysis/')) {
            $analysis = $this->analysis();

            return match ($path) {
                '/analysis/summary' => $analysis->summary($record(), $seat('seat'))->toArray(),
                '/analysis/trick' => $analysis->trick($record(), $seat('seat'), $trick())->toArray(),
                '/analysis/expert-choice' => $analysis->expertChoice($record(), $seat('seat'), $trick())->toArray(),
                '/analysis/what-if' => $analysis->whatIf($record(), $seat('seat'), $trick(), Card::fromCode($string('card')))->toArray(),
                '/analysis/swings' => $analysis->swings($record(), $seat('seat'))->toArray(),
                '/analysis/bids' => $analysis->bids($record(), $seat('seat'), Lang::from($string('lang')))->toArray(),
                default => self::fail('The contract holds an analysis this test does not know: '.$path),
            };
        }

        return match ($path) {
            '/games' => $engine->newGame($seed(), Scoring::from($string('scoring')), isset($r['target']) ? Json::int($r['target'], 'target') : null, isset($r['dealer']) ? $seat('dealer') : null)->toEngineArray(),
            '/games/apply' => $engine->apply($state(), $seat('seat'), ActionMapper::fromArray(Json::object($r['action'], 'action')), $seed())->toEngineArray(),
            '/games/view' => ['view' => $engine->view($state(), $seat('seat'))->toArray()],
            '/games/legal' => $engine->legalMoves($state(), $seat('seat'))->toArray(),
            '/games/robot' => self::robotMove($engine->robotMove($state(), Level::from($string('level')), $seed(), Lang::from($string('lang')))),
            '/deals/replay' => $engine->replay($record())->toArray(),
            default => self::fail('The contract holds a call this test does not know: '.$path),
        };
    }

    /** @return array{seat: int, action: array<string, mixed>} the move as the bridge wrote it */
    private static function robotMove(RobotMove $move): array
    {
        return ['seat' => $move->seat->value, 'action' => ActionMapper::toArray($move->action)];
    }
}
