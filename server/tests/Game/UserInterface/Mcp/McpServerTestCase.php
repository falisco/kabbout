<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Infrastructure\Bridge\BridgeEngine;
use Kabbout\Game\Infrastructure\Bridge\Transport\HttpBridgeTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * What the MCP server promises a client, whatever carries the calls: the same scenarios are played
 * over stdio and over HTTP. Nothing is replaced: the container, the cache and the bridge are the real
 * ones. It needs a bridge running, at the address of BRIDGE_URL: `npm run check:php` starts it.
 *
 * The scripted player passes in the auction and plays its first legal card, at several seats and
 * against the three levels of robots.
 */
#[Group('integration')]
abstract class McpServerTestCase extends WebTestCase
{
    protected const array TOOLS = [
        'new_game', 'my_view', 'legal_moves', 'bid', 'play_card', 'next_deal', 'score',
        'deal_summary', 'trick', 'expert_choice', 'what_if', 'expert_bids', 'biggest_swings',
    ];
    private const string SEED = '5eed5eed5eed5eed5eed5eed5eed5eed';

    /**
     * A client of the server, who is a guest of their own: each call of this is another player.
     *
     * @return \Closure(string, array<string, mixed>=): array{refused: bool, said: string, answer: array<string, mixed>} calls one tool
     */
    abstract protected function player(): \Closure;

    protected function setUp(): void
    {
        $bridge = getenv('BRIDGE_URL');
        if (false === $bridge || '' === $bridge) {
            self::markTestSkipped('No bridge to ask: BRIDGE_URL is not set.');
        }
    }

    /**
     * @return iterable<string, array{int, string}> the seat the player takes and how well the robots play: the
     *                                              player is not always the one the cards are dealt around
     */
    public static function seats(): iterable
    {
        yield 'seat 0, easy robots' => [0, 'easy'];
        yield 'seat 2, normal robots' => [2, 'normal'];
        yield 'seat 3, expert robots' => [3, 'expert'];
    }

    #[DataProvider('seats')]
    public function testAWholeDealIsPlayedAndItsScoreIsTheEngines(int $seat, string $level): void
    {
        $call = $this->player();

        $turn = self::answer($call('new_game', ['level' => $level, 'seat' => $seat, 'seed' => self::SEED]));
        $table = self::str($turn, 'table');
        $dealt = self::arr($turn, 'view', 'hand');
        self::assertCount(8, $dealt);
        self::refused($call('deal_summary', ['table' => $table]), 'No deal has been played to its end');

        $turn = self::playTheDeal($call, $turn, $seat);
        self::assertTrue(self::at($turn, 'legal', 'next'));
        self::assertSame($turn['view'], self::answer($call('my_view', ['table' => $table])));
        self::assertSame($turn['legal'], self::answer($call('legal_moves', ['table' => $table])));

        // the count the table gives is the engine's own, for the deal as the table saw it played
        $score = self::answer($call('score', ['table' => $table]));
        $record = DealRecord::fromArray([
            'scoring' => self::at($turn, 'view', 'scoring'),
            'dealer' => self::at($turn, 'view', 'dealer'),
            'bids' => self::at($turn, 'view', 'bids'),
            'tricks' => array_column(self::arr($turn, 'view', 'tricks'), 'plays'),
        ]);
        $engine = new BridgeEngine(new HttpBridgeTransport(HttpClient::create(), (string) getenv('BRIDGE_URL')));
        self::assertEquals($engine->replay($record)->result, $score['result']);
        self::assertSame(self::at($score, 'result', 'gained'), $score['scores']);
        self::assertSame($seat % 2, $score['team']);

        // and the deal can now be reviewed, from what the server holds of it, for the seat the player sat at
        $summary = self::answer($call('deal_summary', ['table' => $table]));
        self::assertSame($seat, $summary['seat']);
        self::assertSame(self::at($score, 'result', 'made'), $summary['made']);
        self::assertSame(self::at($score, 'result', 'gained', $seat % 2), self::at($summary, 'us', 'gained'));
        self::assertArrayHasKey('swings', self::answer($call('biggest_swings', ['table' => $table])));
        $mine = array_column(self::arr($turn, 'view', 'tricks', 0, 'plays'), 'card', 'seat')[$seat];
        self::assertSame($mine, self::answer($call('trick', ['table' => $table, 'n' => 1]))['played'] ?? null);
        self::assertSame($mine, self::answer($call('expert_choice', ['table' => $table, 'n' => 1]))['played'] ?? null);
        self::assertSame(0, self::answer($call('what_if', ['table' => $table, 'n' => 1, 'card' => $mine]))['difference'], 'The card that was played, played instead of itself.');
        self::assertNotEmpty(self::answer($call('expert_bids', ['table' => $table])));

        // the next deal is dealt from other cards, and the one just played is still the one a review is about
        $next = self::answer($call('next_deal', ['table' => $table]));
        self::assertSame(2, self::at($next, 'view', 'round'));
        self::assertCount(8, self::arr($next, 'view', 'hand'));
        self::assertNotSame($dealt, self::arr($next, 'view', 'hand'), 'The second deal is the first one dealt again.');
        self::assertSame($summary, self::answer($call('deal_summary', ['table' => $table])));

        // the same seed, played the same way by another guest: the same deal
        $again = $this->player();
        $same = self::playTheDeal($again, self::answer($again('new_game', ['level' => $level, 'seat' => $seat, 'seed' => self::SEED])), $seat);
        self::assertSame($turn['view'], $same['view']);
        self::assertNotSame($table, $same['table']);
    }

    public function testABidIsHeardAtThePlayersSeatAsTheyMadeIt(): void
    {
        $call = $this->player();
        $turn = self::answer($call('new_game', ['level' => 'easy', 'seat' => 1, 'seed' => self::SEED]));

        // the highest thing the player may say that is a bid: nobody can have said it before them
        $raises = array_values(array_filter(self::arr($turn, 'legal', 'bids'), static fn (mixed $bid): bool => \is_array($bid) && 'bid' === $bid['type'] && 160 === $bid['value']));
        self::assertNotSame([], $raises);
        /** @var array{type: string, value: int, suit: string} $raise */
        $raise = $raises[0];
        $after = self::answer($call('bid', ['table' => $turn['table'], ...$raise]));

        self::assertContains([...$raise, 'seat' => 1], self::arr($after, 'view', 'bids'));
        self::assertSeesOnlyItsOwn($after, 1);
    }

    public function testAMoveThatIsNotAllowedChangesNothingAndSaysWhatIs(): void
    {
        $call = $this->player();
        $turn = self::answer($call('new_game', ['level' => 'easy', 'seed' => self::SEED]));
        self::assertSame('bidding', self::at($turn, 'view', 'phase'));

        // a card during the auction; a raise with no suit; the next deal before this one is over
        $refusal = self::refused($call('play_card', ['table' => $turn['table'], 'card' => self::at($turn, 'view', 'hand', 0)]), '. Nothing has changed. What you may do now: {"bids":[{"type":"pass"}');
        self::assertStringStartsWith('Refused: ', $refusal);
        self::refused($call('bid', ['table' => $turn['table'], 'type' => 'bid', 'value' => 80]), 'names its value and its trump suit');
        self::refused($call('next_deal', ['table' => $turn['table']]), '. Nothing has changed.');
        self::assertSame($turn['view'], self::answer($call('my_view', ['table' => $turn['table']])));

        // in the play: a card the player does not hold, which the engine refuses with the cards they may play
        $moves = 0;
        while ('playing' !== self::at($turn, 'view', 'phase')) {
            self::assertLessThan(50, ++$moves, 'The auction never ends.');
            $turn = self::answer($call('bid', ['table' => $turn['table'], 'type' => 'pass']));
        }
        $held = self::arr($turn, 'view', 'hand');
        // nine cards, and a hand is eight: one of them at least is somebody else's
        $notHeld = array_values(array_filter(['7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS', 'AS', '7H'], static fn (string $card): bool => !\in_array($card, $held, true)))[0] ?? self::fail('The player holds nine cards.');
        $refusal = self::refused($call('play_card', ['table' => $turn['table'], 'card' => $notHeld]), '. Nothing has changed. What you may do now: {"bids":[],"cards":["');
        self::assertStringContainsString(json_encode(self::at($turn, 'legal', 'cards'), \JSON_THROW_ON_ERROR), $refusal);
        self::assertSame($turn['view'], self::answer($call('my_view', ['table' => $turn['table']])));
    }

    public function testATableIsItsPlayersAlone(): void
    {
        $owner = $this->player();
        $other = $this->player();
        $table = self::str(self::answer($owner('new_game')), 'table');

        $nobodys = self::refused($other('my_view', ['table' => str_repeat('0', 32)]), 'There is no such table. Open one with new_game.');
        foreach (['my_view', 'legal_moves', 'score', 'next_deal', 'deal_summary'] as $tool) {
            self::assertSame($nobodys, self::refused($other($tool, ['table' => $table]), 'There is no such table'), $tool);
        }
        self::assertSame($nobodys, self::refused($other('bid', ['table' => $table, 'type' => 'pass']), 'There is no such table'));
        self::assertSame($nobodys, self::refused($other('my_view', ['table' => 'not a name']), 'There is no such table'));

        self::assertSame($table, self::str(self::answer($owner('score', ['table' => $table])), 'table'));
    }

    /**
     * Plays from where the player stands to the end of the deal, looking at each step at what they are shown.
     *
     * @param \Closure(string, array<string, mixed>=): array{refused: bool, said: string, answer: array<string, mixed>} $call
     * @param array<string, mixed>                                                                                      $turn
     *
     * @return array<string, mixed> where the player stands once the deal is over
     */
    private static function playTheDeal(\Closure $call, array $turn, int $seat = 0): array
    {
        for ($moves = 0; 'roundEnd' !== self::at($turn, 'view', 'phase'); ++$moves) {
            self::assertLessThan(200, $moves, 'The deal never ends.');
            self::assertSeesOnlyItsOwn($turn, $seat);
            self::assertSame($seat, self::at($turn, 'view', 'turn'), 'The robots stop when it is the player\'s turn.');
            // what the tools that only look say in the middle of a deal is what the last action answered
            self::assertSame($turn['view'], self::answer($call('my_view', ['table' => $turn['table']])));
            self::assertSame($turn['legal'], self::answer($call('legal_moves', ['table' => $turn['table']])));
            if ([] !== self::at($turn, 'legal', 'bids')) {
                $turn = self::answer($call('bid', ['table' => $turn['table'], 'type' => 'pass']));
                continue;
            }
            $hand = self::arr($turn, 'view', 'hand');
            $card = self::at($turn, 'legal', 'cards', 0);
            $turn = self::answer($call('play_card', ['table' => $turn['table'], 'card' => $card]));
            // the hand the player is shown next is the one they held, less the card they played: nobody else's
            self::assertSame(array_values(array_filter($hand, static fn (mixed $held): bool => $held !== $card)), self::arr($turn, 'view', 'hand'));
        }
        self::assertSeesOnlyItsOwn($turn, $seat);

        return $turn;
    }

    /**
     * @param array{refused: bool, said: string, answer: array<string, mixed>} $called
     *
     * @return array<string, mixed> what a tool answered, which must be no refusal
     */
    private static function answer(array $called): array
    {
        self::assertFalse($called['refused'], $called['said']);

        return $called['answer'];
    }

    /**
     * @param array{refused: bool, said: string, answer: array<string, mixed>} $called
     *
     * @return string the words of a refusal, which must be one the client's model is shown
     */
    private static function refused(array $called, string $saying): string
    {
        self::assertTrue($called['refused'], $called['said']);
        self::assertStringContainsString($saying, $called['said']);

        return $called['said'];
    }

    /**
     * In all a tool answered (what the table heard, the view, the moves): no other hand, no robot's
     * reason for a bid, no belote before it is announced unless it is the seat's own.
     *
     * @param array<array-key, mixed> $turn
     */
    private static function assertSeesOnlyItsOwn(array $turn, int $seat): void
    {
        $view = self::arr($turn, 'view');
        self::assertArrayNotHasKey('hands', $view);
        self::assertSame($seat, $view['seat']);
        self::assertSame(\count(self::arr($view, 'hand')), self::at($view, 'handSizes', $seat));
        self::assertSame(32, array_sum(self::arr($view, 'handSizes')) + 4 * \count(self::arr($view, 'tricks')) + \count(self::arr($view, 'trick', 'plays')));
        // a card the player holds is in nobody's play, and each card is somewhere once
        $played = array_column(self::arr($view, 'trick', 'plays'), 'card');
        foreach (self::arr($view, 'tricks') as $trick) {
            $played = [...$played, ...array_column(self::arr(['trick' => $trick], 'trick', 'plays'), 'card')];
        }
        self::assertCount(32 - array_sum(self::arr($view, 'handSizes')), array_unique($played, \SORT_REGULAR));
        foreach (self::arr($view, 'hand') as $held) {
            self::assertNotContains($held, $played, 'A card the player holds was played by somebody.');
        }
        $belote = $view['belote'] ?? null;
        self::assertTrue(null === $belote || $seat === self::at($view, 'belote', 'seat') || self::at($view, 'belote', 'played') > 0, 'A belote nobody has announced is shown.');
        // a robot's reason, or a hand, under any key of the whole answer
        $written = json_encode($turn, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('"why"', $written);
        self::assertStringNotContainsString('"hands"', $written);
        self::assertSame(1, substr_count($written, '"hand"'));
    }

    /**
     * What an answer holds at a path of keys; nothing when the path leads nowhere.
     *
     * @param array<array-key, mixed> $data
     */
    private static function at(array $data, int|string ...$path): mixed
    {
        $found = $data;
        foreach ($path as $key) {
            $found = \is_array($found) ? $found[$key] ?? null : null;
        }

        return $found;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed> the list or the map at a path; empty when there is none
     */
    private static function arr(array $data, int|string ...$path): array
    {
        $found = self::at($data, ...$path);

        return \is_array($found) ? $found : [];
    }

    /** @param array<array-key, mixed> $data */
    private static function str(array $data, int|string ...$path): string
    {
        $found = self::at($data, ...$path);
        self::assertIsString($found);

        return $found;
    }
}
