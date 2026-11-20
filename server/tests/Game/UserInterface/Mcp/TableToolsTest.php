<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\BidType;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Suit;
use Kabbout\Game\UserInterface\Mcp\TableTools;
use Kabbout\Tests\Game\Application\Table\TurnsEngine;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What each table tool hands its capability: the arguments as the client gave them, none dropped and
 * none replaced. The server is the real one but for the engine, which here writes down what it is asked.
 */
final class TableToolsTest extends KernelTestCase
{
    private TurnsEngine $engine;
    private TableTools $tools;

    protected function setUp(): void
    {
        $_SERVER['KABBOUT_TABLES_LIMIT'] = $_SERVER['KABBOUT_TABLES_OPENED'] = $_SERVER['KABBOUT_GUESTS_LIMIT'] = '100000';
        self::bootKernel();
        $this->engine = new TurnsEngine();
        self::getContainer()->set(Engine::class, $this->engine);
        $tools = self::getContainer()->get(TableTools::class);
        self::assertInstanceOf(TableTools::class, $tools);
        $this->tools = $tools;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['KABBOUT_TABLES_LIMIT'], $_SERVER['KABBOUT_TABLES_OPENED'], $_SERVER['KABBOUT_GUESTS_LIMIT']);
        parent::tearDown();
    }

    public function testTheLevelTheSeatAndTheSeedAreTheOnesTheClientChose(): void
    {
        $opened = self::answer($this->tools->newGame(Level::Expert, Seat::Fourth, str_repeat('ab', 16)));

        // seats 1 and 2 were robots of that level, and it is seat 3's turn
        self::assertSame([['robot', 1, 'expert fr'], ['robot', 2, 'expert fr']], array_map(static fn (array $call): array => \array_slice($call, 0, 3), array_values(array_filter($this->engine->drawn, static fn (array $call): bool => 'robot' === $call[0]))));
        self::assertSame([3, 3], [self::at($opened, 'view', 'seat'), self::at($opened, 'view', 'turn')]);

        // the same seed again draws the same; no seed draws from elsewhere
        $seeds = array_column($this->engine->drawn, 3);
        $this->engine->drawn = [];
        $this->tools->newGame(Level::Expert, Seat::Fourth, str_repeat('ab', 16));
        self::assertSame($seeds, array_column($this->engine->drawn, 3));
        $this->engine->drawn = [];
        $this->tools->newGame(Level::Expert, Seat::Fourth);
        self::assertSame([], array_intersect($seeds, array_column($this->engine->drawn, 3)));
    }

    public function testLeftToItselfATableIsSeatZeroAgainstNormalRobots(): void
    {
        $opened = self::answer($this->tools->newGame());

        self::assertSame(0, self::at($opened, 'view', 'seat'));
        self::assertSame('normal fr', $this->engine->drawn[1][2]);
    }

    public function testABidACardAndTheNextDealReachTheEngineAsTheyWereGiven(): void
    {
        $table = self::answer($this->tools->newGame(Level::Easy, Seat::Second))['table'];
        self::assertIsString($table);
        $this->engine->applied = [];

        $this->tools->bid($table, BidType::Raise, 110, Suit::Hearts);
        $mine = $this->applied();
        self::assertSame([BidType::Raise, 110, Suit::Hearts, null], [$mine->bid?->type, $mine->bid?->value, $mine->bid?->suit, $mine->reason]);

        $this->engine->applied = [];
        $this->tools->playCard($table, 'JH');
        self::assertSame('JH', $this->applied()->card?->code());

        // the deal of this engine lasts six actions: it is over, and only then may the next one be asked for
        $over = self::answer($this->tools->myView($table));
        self::assertSame('roundEnd', $over['phase']);
        self::assertTrue(self::answer($this->tools->legalMoves($table))['next']);
        self::assertSame(1, self::answer($this->tools->score($table))['team']);
        self::assertSame(2, self::at(self::answer($this->tools->nextDeal($table)), 'view', 'round'));
    }

    public function testARaiseThatNamesNoSuitIsRefusedInWordsBeforeTheEngineIsAsked(): void
    {
        $table = self::answer($this->tools->newGame(Level::Easy, Seat::Second))['table'];
        self::assertIsString($table);
        $this->engine->applied = [];

        try {
            $this->tools->bid($table, BidType::Raise, 110);
            self::fail('A raise with no suit went to the engine.');
        } catch (ToolCallException $refused) {
            self::assertStringContainsString('names its value and its trump suit', $refused->getMessage());
        }
        self::assertSame([], $this->engine->applied);
    }

    /** The first action the engine was asked to apply since the list was last emptied: the player's own. */
    private function applied(): Action
    {
        return $this->engine->applied[0] ?? self::fail('Nothing reached the engine.');
    }

    /** @return array<array-key, mixed> */
    private static function answer(CallToolResult $result): array
    {
        self::assertFalse($result->isError);
        self::assertIsArray($result->structuredContent);

        return $result->structuredContent;
    }

    /** @param array<array-key, mixed> $data */
    private static function at(array $data, string ...$path): mixed
    {
        $found = $data;
        foreach ($path as $key) {
            $found = \is_array($found) ? $found[$key] ?? null : null;
        }

        return $found;
    }
}
