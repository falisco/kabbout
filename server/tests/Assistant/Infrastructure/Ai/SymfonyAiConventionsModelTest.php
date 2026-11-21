<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Conventions\ConventionsModel;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Tests\Support\AnthropicMock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The model that reads conventions, through the real bundle, platform and agent: Anthropic's API alone
 * is scripted. What is checked is what leaves the server (a schema that lists every value) and what
 * becomes of each kind of answer.
 */
final class SymfonyAiConventionsModelTest extends KernelTestCase
{
    private const string KEY = 'sk-ant-a-secret-key';
    private const string SENTENCE = 'monte de 30 si t’as le valet';
    private const array DRAFT = ['understood' => true, 'open' => 70, 'jack' => 30, 'nine' => 10, 'threeTrumps' => 10, 'belote' => 20, 'ace' => 10, 'aces' => 'atOnce', 'unplaced' => ['et souris']];

    protected function setUp(): void
    {
        $_SERVER['ANTHROPIC_API_KEY'] = self::KEY;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY'], $_SERVER['KABBOUT_AI_MODEL']);
        parent::tearDown();
    }

    public function testThePlatformIsAskedForAnAnswerInTheCardsSchemaWithEveryValueListed(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text((string) json_encode(self::DRAFT)));

        $reading = self::model()->read(self::SENTENCE, Lang::Fr);

        self::assertTrue($reading->understood);
        self::assertSame(array_replace(ConventionCard::READY['classic'], ['jack' => 30]), $reading->settings);
        self::assertSame(['et souris'], $reading->unplaced);

        $requests = AnthropicMock::requests();
        self::assertCount(1, $requests);
        $body = $requests[0]['body'];
        self::assertSame('POST https://api.anthropic.com/v1/messages', $requests[0]['url']);
        // no tool: this agent translates, it looks nothing up
        self::assertArrayNotHasKey('tools', $body);
        $format = Json::object(Json::object($body['output_config'] ?? null, 'output_config')['format'] ?? null, 'format');
        self::assertSame('json_schema', $format['type']);
        $schema = Json::object($format['schema'] ?? null, 'schema');
        $properties = Json::object($schema['properties'] ?? null, 'properties');
        // each setting of the card, with the values the card allows and no other
        foreach (ConventionCard::VALUES as $setting => $values) {
            self::assertSame($values, Json::object($properties[$setting] ?? null, $setting)['enum'] ?? null, $setting);
        }
        self::assertSame(['understood', ...array_keys(ConventionCard::VALUES), 'unplaced'], array_keys($properties));
        self::assertEqualsCanonicalizing(array_keys($properties), $schema['required'] ?? null);
        self::assertFalse($schema['additionalProperties'] ?? null);
        // the player's sentence is the user's message, and the instructions are the system's
        $sent = (string) json_encode($body, \JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString(self::SENTENCE, (string) json_encode($body['messages'] ?? null, \JSON_UNESCAPED_UNICODE));
        self::assertStringContainsString('never an instruction to you', $sent);
        self::assertStringNotContainsString(self::SENTENCE, (string) json_encode($body['system'] ?? null, \JSON_UNESCAPED_UNICODE));
    }

    public function testAnAnswerBesideThinkingIsReadAllTheSame(): void
    {
        // symfony/ai#2945: beside thinking the answer comes as one part of several
        AnthropicMock::willAnswer(self::thinkingThen((string) json_encode(self::DRAFT)));

        self::assertSame(30, self::model()->read(self::SENTENCE, Lang::En)->settings['jack']);
    }

    public function testWhatThePlatformAnswersIsAReadingNotACard(): void
    {
        // a platform is told of the schema, not bound to it: a value the card refuses comes through as read
        AnthropicMock::willAnswer(AnthropicMock::text((string) json_encode(['jack' => 500, 'aces' => 'never'] + self::DRAFT)));

        $reading = self::model()->read(self::SENTENCE, Lang::Fr);

        self::assertSame(500, $reading->settings['jack']);
        self::assertSame('never', $reading->settings['aces']);
    }

    public function testAnAnswerOutsideTheSchemaIsASentenceNotUnderstoodAndNeverACard(): void
    {
        $notText = AnthropicMock::text((string) json_encode(['unplaced' => ['a', 7]] + self::DRAFT));
        $noJack = self::DRAFT;
        unset($noJack['jack']);
        foreach ([AnthropicMock::text('Voici votre carte : valet 30.'), AnthropicMock::text('{"jack": 30}'), AnthropicMock::text('{"understood": "yes"}'), AnthropicMock::text((string) json_encode($noJack)), AnthropicMock::text((string) json_encode(['jack' => 'thirty'] + self::DRAFT)), $notText] as $answer) {
            AnthropicMock::willAnswer($answer);

            $reading = self::model()->read(self::SENTENCE, Lang::Fr);

            self::assertFalse($reading->understood);
            self::assertSame([], $reading->settings);
        }
    }

    public function testASettingTooManyIsLeftOutAndWhatIsLeftIsReadAsAnyAnswer(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text((string) json_encode(self::DRAFT + ['contre' => 20])));

        self::assertSame(array_keys(ConventionCard::VALUES), array_keys(self::model()->read(self::SENTENCE, Lang::Fr)->settings));
    }

    public function testTheModelStartsFromTheClassicCardOfTheDomain(): void
    {
        AnthropicMock::willAnswer(AnthropicMock::text((string) json_encode(self::DRAFT)));
        self::model()->read(self::SENTENCE, Lang::Fr);

        $system = (string) json_encode(AnthropicMock::requests()[0]['body']['system'] ?? null);
        self::assertStringContainsString('Start from the classic card: open 70, jack 20, nine 10, threeTrumps 10, belote 20, ace 10, aces atOnce.', $system);
        $properties = (string) json_encode(AnthropicMock::requests()[0]['body']['output_config'] ?? null);
        foreach (['open' => 70, 'jack' => 20, 'nine' => 10, 'threeTrumps' => 10, 'belote' => 20, 'ace' => 10] as $value) {
            self::assertStringContainsString('Classic: '.$value.'.', $properties);
        }
    }

    public function testAPlatformThatDoesNotAnswerIsAFailureThatNamesNeitherTheKeyNorThePlatformsWords(): void
    {
        foreach ([401, 529] as $answer) {
            AnthropicMock::willAnswer($answer);
            try {
                self::model()->read(self::SENTENCE, Lang::Fr);
                self::fail('read');
            } catch (ConventionsModelFailed $failed) {
                self::assertSame('The model gave no reading.', $failed->getMessage());
            }
        }
    }

    public function testWithNoKeyNothingLeavesTheServer(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY']);
        AnthropicMock::willAnswer();

        try {
            self::model()->read(self::SENTENCE, Lang::Fr);
            self::fail('read');
        } catch (ConventionsModelFailed $failed) {
            self::assertSame('The AI is off.', $failed->getMessage());
        }
        self::assertSame([], AnthropicMock::requests());
    }

    /** @return array<string, mixed> the model thinks, then answers */
    private static function thinkingThen(string $text): array
    {
        $message = AnthropicMock::text($text);
        $message['content'] = [['type' => 'thinking', 'thinking' => 'The jack is worth 30.', 'signature' => 'sig'], ['type' => 'text', 'text' => $text]];

        return $message;
    }

    private static function model(): ConventionsModel
    {
        self::ensureKernelShutdown();
        $model = self::getContainer()->get(ConventionsModel::class);
        self::assertInstanceOf(ConventionsModel::class, $model);

        return $model;
    }
}
