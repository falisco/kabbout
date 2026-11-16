<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModel;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Assistant\Application\Coach\Rejected;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Assistant\Infrastructure\Ai\CoachConversation;
use Kabbout\Assistant\Infrastructure\Ai\CoachPrompt;
use Kabbout\Assistant\Infrastructure\Ai\SymfonyAiCoachModel;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeTransport;
use Kabbout\Tests\Support\AnthropicMock;
use Kabbout\Tests\Support\RecordedDeal;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The coach's model as the server runs it: the agent of the Symfony AI bundle, its Anthropic platform
 * and its five tools, taken from the container. Only two things are not the real ones: Anthropic's API,
 * which answers from a script in its own format, and the bridge, which answers from the recorded contract
 * (the deal analysed for seat 0).
 */
final class SymfonyAiCoachModelTest extends KernelTestCase
{
    private const string KEY = 'sk-ant-a-secret-key';

    protected function setUp(): void
    {
        $_SERVER['ANTHROPIC_API_KEY'] = self::KEY;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY'], $_SERVER['KABBOUT_AI_MODEL']);
        parent::tearDown();
    }

    public function testWhatWasAlreadySaidAboutTheDealComesBeforeTheQuestion(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text('Parce que l’as était maître.'));
        $earlier = [new Exchange('Qu’ai-je mal joué ?', 'Au pli 4, le 10 de trèfle.')];
        $brief = new CoachBrief(RecordedDeal::record(), Seat::First, Lang::Fr, 'Et pourquoi ?', $earlier);

        self::model()->answer($brief, new Facts());

        $messages = Json::objects(AnthropicMock::requests()[0]['body']['messages'] ?? null, 'messages');
        self::assertSame(['user', 'assistant', 'user'], array_column($messages, 'role'));
        $sent = (string) json_encode($messages, \JSON_UNESCAPED_UNICODE);
        self::assertTrue(strpos($sent, 'Qu’ai-je mal joué ?') < strpos($sent, 'Au pli 4, le 10 de trèfle.'));
        self::assertTrue(strpos($sent, 'Au pli 4, le 10 de trèfle.') < strpos($sent, 'Et pourquoi ?'));
    }

    public function testTheModelLooksAtTheDealThroughItsToolsAndEveryResultBecomesAFact(): void
    {
        AnthropicMock::willAnswer(
            AnthropicMock::toolUse(['biggest_swings', []], ['deal_summary', []]),
            AnthropicMock::toolUse(['trick', ['n' => 1]], ['expert_choice', ['n' => 4]]),
            AnthropicMock::text('Au pli 4, le 10 de trèfle.'),
        );
        $facts = new Facts();

        self::assertSame('Au pli 4, le 10 de trèfle.', self::model()->answer(self::brief(), $facts));

        $requests = AnthropicMock::requests();
        self::assertCount(3, $requests);
        // what leaves the server: Anthropic's Messages API, the key in its header, the model of the settings
        self::assertSame('POST https://api.anthropic.com/v1/messages', $requests[0]['url']);
        self::assertContains('x-api-key: '.self::KEY, $requests[0]['headers']);
        self::assertSame('claude-sonnet-5-5', $requests[0]['body']['model']);
        self::assertSame(4096, $requests[0]['body']['max_tokens']);
        self::assertSame(['deal_summary', 'trick', 'expert_choice', 'what_if', 'biggest_swings'], array_column(Json::objects($requests[0]['body']['tools'] ?? null, 'tools'), 'name'));
        // the first request is the rules and the question; the next ones carry what the tools returned
        $sent = array_map(static fn (array $request): string => (string) json_encode($request['body'], \JSON_UNESCAPED_UNICODE), $requests);
        self::assertStringContainsString('You are the coach of Kabbout', $sent[0]);
        self::assertStringContainsString('Qu’ai-je mal joué ?', $sent[0]);
        self::assertStringNotContainsString(self::KEY, $sent[0]);
        self::assertStringContainsString('tool_result', $sent[1]);
        self::assertStringContainsString('swings', $sent[1]);
        self::assertStringContainsString('onTable', $sent[2]);
        // each result names its cards in the player's language: the model translates no code
        self::assertStringContainsString('10 de trèfle', $sent[1]);
        self::assertStringContainsString('as de carreau', $sent[1]);
        self::assertTrue($facts->hasTrick(4) && $facts->hasTrick(1) && $facts->hasCard('TC') && $facts->hasCard('AD'));
        self::assertTrue($facts->hasNumber(Json::int(Json::object(RecordedDeal::call('/analysis/summary')['response']['us'] ?? null, 'side')['gained'] ?? null, 'score')));
    }

    public function testAToolSaysWhatItsArgumentsAre(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text('Vu.'));

        self::model()->answer(self::brief(), new Facts());

        $tools = array_column(Json::objects(AnthropicMock::requests()[0]['body']['tools'] ?? null, 'tools'), 'input_schema', 'name');
        $whatIf = Json::object($tools['what_if'] ?? null, 'schema');
        self::assertSame(['n', 'card'], $whatIf['required'] ?? null);
        $properties = Json::object($whatIf['properties'] ?? null, 'properties');
        self::assertSame('integer', Json::object($properties['n'] ?? null, 'n')['type'] ?? null);
        self::assertStringContainsString('from 1 to 8', (string) json_encode($properties['n'] ?? null));
        self::assertStringContainsString('for example QH', (string) json_encode($properties['card'] ?? null));
    }

    public function testAQuestionTheModelAsksWronglyIsAnsweredInWordsAndEstablishesNothing(): void
    {
        $refused = RecordedDeal::call('/analysis/what-if', 422)['request'];
        AnthropicMock::willAnswer(
            AnthropicMock::toolUse(['trick', ['n' => 9]], ['expert_choice', ['n' => 0]], ['what_if', ['n' => 12, 'card' => 'QH']], ['what_if', ['n' => 1, 'card' => 'queen']]),
            AnthropicMock::toolUse(['what_if', ['n' => $refused['trick'], 'card' => $refused['card']]], ['no_such_tool', []]),
            AnthropicMock::text('Rien.'),
        );
        $facts = new Facts();

        self::assertSame('Rien.', self::model()->answer(self::brief(), $facts));

        self::assertTrue($facts->isEmpty());
        $second = (string) json_encode(AnthropicMock::requests()[1]['body']);
        $third = (string) json_encode(AnthropicMock::requests()[2]['body']);
        self::assertStringContainsString('No trick 9: a deal has tricks 1 to 8.', $second);
        self::assertStringContainsString('No trick 0', $second);
        self::assertStringContainsString('No trick 12', $second);
        self::assertStringContainsString('No such card', $second);
        self::assertStringContainsString('The engine refused: illegal card', $third);
        self::assertStringContainsString('was not found', $third);
    }

    public function testAWhatIfGivesBothEndings(): void
    {
        $asked = RecordedDeal::call('/analysis/what-if')['request'];
        AnthropicMock::willAnswer(AnthropicMock::toolUse(['what_if', ['n' => $asked['trick'], 'card' => ' '.strtolower(\is_string($asked['card']) ? $asked['card'] : '').' ']]), AnthropicMock::text('Vu.'));
        $facts = new Facts();

        self::model()->answer(self::brief(), $facts);

        self::assertStringContainsString('asPlayed', (string) json_encode(AnthropicMock::requests()[1]['body']));
        self::assertFalse($facts->isEmpty());
    }

    public function testACorrectionGoesOnWithTheSameConversation(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::toolUse(['biggest_swings', []]), AnthropicMock::text('Le pli 4 coûte 999 points.'), AnthropicMock::text('Le pli 4.'));
        $facts = new Facts();
        $brief = self::brief();
        $model = self::model();

        $first = $model->answer($brief, $facts);
        self::assertSame('Le pli 4.', $model->answer($brief, $facts, new Rejected($first, ['999'])));

        $again = (string) json_encode(AnthropicMock::requests()[2]['body'], \JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('it states 999, which no tool result', $again);
        self::assertStringContainsString('Le pli 4 coûte 999 points.', $again);
        // what the tools returned the first time is still there: the model need not ask again
        self::assertStringContainsString('swings', $again);
        self::assertStringContainsString('You are the coach of Kabbout', $again);
    }

    public function testACorrectionWithNoConversationKeptStartsFromTheRejectedAnswer(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text('Trick 4.'));

        self::model()->answer(self::brief(Lang::En), new Facts(), new Rejected('Trick 4 costs 999.', ['999', 'KS']));

        $sent = (string) json_encode(AnthropicMock::requests()[0]['body']);
        self::assertStringContainsString('Trick 4 costs 999.', $sent);
        self::assertStringContainsString('it states 999, KS, which no tool result', $sent);
        self::assertStringContainsString('Answer in English', $sent);
    }

    public function testTheModelAskedIsTheOneOfTheSettings(): void
    {
        $_SERVER['KABBOUT_AI_MODEL'] = 'claude-sonnet-5-5';
        AnthropicMock::willAnswer(AnthropicMock::text('Vu.'));

        self::model()->answer(self::brief(), new Facts());

        self::assertSame('claude-sonnet-5-5', AnthropicMock::requests()[0]['body']['model']);
    }

    /** @param list<array<string, mixed>|int> $script */
    #[DataProvider('failures')]
    public function testWhateverGoesWrongWithTheModelIsOneFailureAndTheConversationIsClosed(array $script, bool $key, int $asked): void
    {
        if (!$key) {
            unset($_SERVER['ANTHROPIC_API_KEY']);
        }
        AnthropicMock::willAnswer(...$script);
        $model = self::model();

        try {
            $model->answer(self::brief(), new Facts());
            self::fail('The model should have failed.');
        } catch (CoachModelFailed $failed) {
            self::assertStringNotContainsString(self::KEY, $failed->getMessage());
        }
        self::assertCount($asked, AnthropicMock::requests());
        // no deal is left behind for a tool to read
        $conversation = self::getContainer()->get(CoachConversation::class);
        self::assertInstanceOf(CoachConversation::class, $conversation);
        $this->expectException(\LogicException::class);
        $conversation->record();
    }

    /** @return iterable<string, array{list<array<string, mixed>|int>, bool, int}> */
    public static function failures(): iterable
    {
        yield 'the platform refuses the key' => [[401], true, 1];
        yield 'the platform is down' => [[529], true, 1];
        yield 'the model calls tools without end' => [array_fill(0, 12, AnthropicMock::toolUse(['biggest_swings', []])), true, 9];
        yield 'the AI is off' => [[], false, 0];
    }

    public function testTheModelIsToldItsJobTheSeatsAndTheLanguage(): void
    {
        $prompt = CoachPrompt::system(new CoachBrief(RecordedDeal::record(), Seat::Fourth, Lang::Ar, 'شنوة غلطت؟'));

        self::assertStringContainsString('The player is seat 3, the partner is seat 1, the opponents are seat 0 (who plays after the player) and seat 2 (who plays before).', $prompt);
        self::assertStringContainsString('Tunisian Arabic (derja)', $prompt);
        self::assertStringContainsString('Every number and every card in your answer must be in a tool result', $prompt);
        self::assertStringNotContainsString('%', $prompt);
        foreach ([[Lang::Fr, 'saying "vous"'], [Lang::En, 'Answer in English']] as [$lang, $words]) {
            self::assertStringContainsString($words, CoachPrompt::system(self::brief($lang)));
        }
    }

    private static function model(): CoachModel
    {
        self::bootKernel();
        self::getContainer()->set(BridgeTransport::class, RecordedDeal::transport());
        $model = self::getContainer()->get(CoachModel::class);
        self::assertInstanceOf(SymfonyAiCoachModel::class, $model);

        return $model;
    }

    private static function brief(Lang $lang = Lang::Fr): CoachBrief
    {
        return new CoachBrief(RecordedDeal::record(), Seat::First, $lang, 'Qu’ai-je mal joué ?');
    }
}
