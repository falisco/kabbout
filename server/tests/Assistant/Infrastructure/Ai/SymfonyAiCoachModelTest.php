<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\Rejected;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\AiSettings;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Assistant\Domain\Platform;
use Kabbout\Assistant\Infrastructure\Ai\AiPlatformFactory;
use Kabbout\Assistant\Infrastructure\Ai\CoachPrompt;
use Kabbout\Assistant\Infrastructure\Ai\CoachToolsFactory;
use Kabbout\Assistant\Infrastructure\Ai\HuggingFacePlatformFactory;
use Kabbout\Assistant\Infrastructure\Ai\SymfonyAiCoachModel;
use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\TryWhatIf;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Tests\Support\RecordedDeal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The agent with a scripted platform in place of the model: what is checked is the loop around it.
 * The tools answer from the deal of the contract, analysed for seat 0.
 */
final class SymfonyAiCoachModelTest extends TestCase
{
    /** @var list<array{payload: string, tools: list<string>}> what the platform was sent, call by call */
    private array $sent = [];

    public function testTheModelLooksAtTheDealThroughItsToolsAndEveryResultBecomesAFact(): void
    {
        $facts = new Facts();
        $model = $this->model([
            self::calls(['biggest_swings', []], ['deal_summary', []]),
            self::calls(['trick', ['n' => 1]], ['expert_choice', ['n' => 4]]),
            'Au pli 4, le 10 de trèfle.',
        ]);

        self::assertSame('Au pli 4, le 10 de trèfle.', $model->answer(self::brief(), $facts));

        self::assertCount(3, $this->sent);
        self::assertSame(['deal_summary', 'trick', 'expert_choice', 'what_if', 'biggest_swings'], $this->sent[0]['tools']);
        // the first request is the rules and the question; the next ones carry what the tools returned
        self::assertStringContainsString('You are the coach of Kabbout', $this->sent[0]['payload']);
        self::assertStringContainsString('Qu’ai-je mal joué ?', $this->sent[0]['payload']);
        self::assertStringContainsString('swings', $this->sent[1]['payload']);
        self::assertStringContainsString('onTable', $this->sent[2]['payload']);
        self::assertTrue($facts->hasTrick(4) && $facts->hasTrick(1) && $facts->hasCard('TC') && $facts->hasCard('AD'));
        self::assertTrue($facts->hasNumber(Json::int(Json::object(RecordedDeal::call('/analysis/summary')['response']['us'] ?? null, 'side')['gained'] ?? null, 'score')));
    }

    public function testAQuestionTheModelAsksWronglyIsAnsweredInWordsAndEstablishesNothing(): void
    {
        $refused = RecordedDeal::call('/analysis/what-if', 422)['request'];
        $facts = new Facts();
        $model = $this->model([
            self::calls(['trick', ['n' => 9]], ['expert_choice', ['n' => 0]], ['what_if', ['n' => 12, 'card' => 'QH']], ['what_if', ['n' => 1, 'card' => 'queen']]),
            self::calls(['what_if', ['n' => $refused['trick'], 'card' => $refused['card']]], ['no_such_tool', []]),
            'Rien.',
        ]);

        self::assertSame('Rien.', $model->answer(self::brief(), $facts));

        self::assertTrue($facts->isEmpty());
        self::assertStringContainsString('No trick 9: a deal has tricks 1 to 8.', $this->sent[1]['payload']);
        self::assertStringContainsString('No trick 0', $this->sent[1]['payload']);
        self::assertStringContainsString('No trick 12', $this->sent[1]['payload']);
        self::assertStringContainsString('No such card', $this->sent[1]['payload']);
        self::assertStringContainsString('The engine refused: illegal card', $this->sent[2]['payload']);
        self::assertStringContainsString('was not found', $this->sent[2]['payload']);
    }

    public function testAWhatIfGivesBothEndings(): void
    {
        $asked = RecordedDeal::call('/analysis/what-if')['request'];
        $facts = new Facts();
        $model = $this->model([self::calls(['what_if', ['n' => $asked['trick'], 'card' => ' '.strtolower(\is_string($asked['card']) ? $asked['card'] : '').' ']]), 'Vu.']);

        $model->answer(self::brief(), $facts);

        self::assertStringContainsString('asPlayed', $this->sent[1]['payload']);
        self::assertFalse($facts->isEmpty());
    }

    public function testACorrectionGoesOnWithTheSameConversation(): void
    {
        $facts = new Facts();
        $brief = self::brief();
        $model = $this->model([self::calls(['biggest_swings', []]), 'Le pli 4 coûte 999 points.', 'Le pli 4.']);

        $first = $model->answer($brief, $facts);
        self::assertSame('Le pli 4.', $model->answer($brief, $facts, new Rejected($first, ['999'])));

        $again = $this->sent[2]['payload'];
        self::assertStringContainsString('it states 999, which no tool result', $again);
        self::assertStringContainsString('Le pli 4 coûte 999 points.', $again);
        // what the tools returned the first time is still there: the model need not ask again
        self::assertStringContainsString('swings', $again);
        self::assertStringContainsString('You are the coach of Kabbout', $again);
    }

    public function testACorrectionWithNoConversationKeptStartsFromTheRejectedAnswer(): void
    {
        $model = $this->model(['Le pli 4.']);

        $model->answer(self::brief(Lang::En), new Facts(), new Rejected('Trick 4 costs 999.', ['999', 'KS']));

        self::assertStringContainsString('Trick 4 costs 999.', $this->sent[0]['payload']);
        self::assertStringContainsString('it states 999, KS, which no tool result', $this->sent[0]['payload']);
        self::assertStringContainsString('Answer in English', $this->sent[0]['payload']);
    }

    /** @param list<ResultInterface|string|\Throwable> $script */
    #[DataProvider('failures')]
    public function testWhateverGoesWrongWithTheModelIsOneFailure(array $script, ?AiSettings $settings): void
    {
        $model = $this->model($script, $settings);

        $this->expectException(CoachModelFailed::class);
        $model->answer(self::brief(), new Facts());
    }

    /** @return iterable<string, array{list<ResultInterface|string|\Throwable>, ?AiSettings}> */
    public static function failures(): iterable
    {
        $on = self::settings();
        yield 'the platform fails' => [[new \RuntimeException('401 Unauthorized, key hf_secret')], $on];
        yield 'the model calls tools without end' => [array_fill(0, SymfonyAiCoachModel::MAX_TOOL_CALLS + 2, self::calls(['biggest_swings', []])), $on];
        yield 'the AI is off' => [['never asked'], null];
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

    public function testTheHuggingFacePlatformIsAskedWithTheKeyTheProviderAndTheModelOfTheSettings(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options['headers'] ?? [], $options['body'] ?? '', $options['timeout'] ?? null, $options['max_duration'] ?? null];

            return new MockResponse(json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Bien joué.'], 'finish_reason' => 'stop']]], \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $factory = new HuggingFacePlatformFactory($http);

        // a model of the bundled list, then one published since
        foreach (['openai/gpt-oss-120b', 'someone/a-model-of-tomorrow'] as $name) {
            $settings = new AiSettings(Platform::HuggingFace, 'hf_secret', $name, 'groq');
            $coach = new SymfonyAiCoachModel(self::configuration($settings), $factory, self::tools(), new NullLogger());
            self::assertSame('Bien joué.', $coach->answer(self::brief(), new Facts()));
        }

        self::assertCount(2, $requests);
        foreach ($requests as $i => [$method, $url, $headers, $body, $timeout, $maxDuration]) {
            self::assertSame('POST', $method);
            self::assertStringStartsWith('https://router.huggingface.co/', $url);
            self::assertContains('Authorization: Bearer hf_secret', \is_array($headers) ? $headers : []);
            $sent = Json::object(json_decode(\is_string($body) ? $body : '', true, flags: \JSON_THROW_ON_ERROR), 'request');
            self::assertSame(['openai/gpt-oss-120b:groq', 'someone/a-model-of-tomorrow:groq'][$i], $sent['model']);
            self::assertCount(5, Json::list($sent['tools'] ?? null, 'tools'));
            self::assertSame([20.0, 45.0], [$timeout, $maxDuration]);
        }
    }

    /**
     * @param list<ResultInterface|string|\Throwable> $script what the platform answers, call by call
     */
    private function model(array $script, ?AiSettings $settings = null): SymfonyAiCoachModel
    {
        $settings = \func_num_args() >= 2 ? $settings : self::settings();
        $platform = MockPlatformFactory::createPlatform(function (object $model, array|string|object $payload, array $options) use (&$script): ResultInterface|string {
            $tools = array_map(self::toolName(...), \is_array($options['tools'] ?? null) ? array_values($options['tools']) : []);
            $this->sent[] = ['payload' => (string) json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR), 'tools' => $tools];
            $next = array_shift($script) ?? throw new \LogicException('The platform was asked more often than scripted.');

            return $next instanceof \Throwable ? throw $next : $next;
        });
        $platforms = new readonly class($platform) implements AiPlatformFactory {
            public function __construct(private PlatformInterface $platform)
            {
            }

            public function create(AiSettings $settings): PlatformInterface
            {
                return $this->platform;
            }
        };

        return new SymfonyAiCoachModel(self::configuration($settings), $platforms, self::tools(), new NullLogger());
    }

    /** the name of a tool as the platform is given it: the object, or its description once the contract has written it out */
    private static function toolName(mixed $tool): string
    {
        if ($tool instanceof Tool) {
            return $tool->getName();
        }
        $described = \is_array($tool) && \is_array($tool['function'] ?? null) ? $tool['function'] : $tool;
        $name = \is_array($described) ? ($described['name'] ?? null) : null;

        return \is_string($name) ? $name : '?';
    }

    private static function configuration(?AiSettings $settings): AiConfiguration
    {
        return new readonly class($settings) implements AiConfiguration {
            public function __construct(private ?AiSettings $settings)
            {
            }

            public function settings(): ?AiSettings
            {
                return $this->settings;
            }
        };
    }

    private static function tools(): CoachToolsFactory
    {
        $analysis = RecordedDeal::analysis();

        return new CoachToolsFactory(RecordedDeal::summarise(), new ReviewTrick($analysis), new AskExpertChoice($analysis), new TryWhatIf($analysis), RecordedDeal::rankSwings());
    }

    private static function settings(): AiSettings
    {
        return new AiSettings(Platform::HuggingFace, 'hf_secret', 'openai/gpt-oss-120b');
    }

    private static function brief(Lang $lang = Lang::Fr): CoachBrief
    {
        return new CoachBrief(RecordedDeal::record(), Seat::First, $lang, 'Qu’ai-je mal joué ?');
    }

    /** @param array{string, array<string, mixed>} ...$calls */
    private static function calls(array ...$calls): ToolCallResult
    {
        return new ToolCallResult(array_map(static fn (array $call, int $i): ToolCall => new ToolCall('call_'.$i, $call[0], $call[1]), array_values($calls), array_keys(array_values($calls))));
    }
}
