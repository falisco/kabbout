<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\UserInterface;

use Kabbout\Assistant\Application\Conventions\ConventionsModel;
use Kabbout\Assistant\Application\Conventions\Reading;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Lang;
use Kabbout\Tests\Assistant\Application\Conventions\ScriptedConventionsModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * POST /api/conventions/interpret as a client sees it. The model is scripted: what is checked is what
 * the door lets in and what it says back.
 */
final class ConventionsEndpointTest extends WebTestCase
{
    /** what a browser sends with a request the game's own page made, and the header the page adds */
    private const array FROM_THE_PAGE = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_CSRF_TOKEN' => 'csrf-token'];
    private const array ASKED = ['sentence' => 'Monte de 30 si t’as le valet, et souris', 'lang' => 'fr'];
    private const array JACK_30 = ['jack' => 30] + ConventionCard::READY['classic'];

    /** the address the sentences come from: a new one for each test, so that no test meets the count of another */
    private static string $visitor = '';

    protected function setUp(): void
    {
        $_SERVER['ANTHROPIC_API_KEY'] = 'sk-ant-a-secret-key';
        self::$visitor = self::someone();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY'], $_SERVER['KABBOUT_AI_MODEL'], $_SERVER['KABBOUT_COACH_LIMIT'], $_SERVER['KABBOUT_COACH_DAILY']);
        parent::tearDown();
    }

    public function testASentenceIsAnsweredWithItsCardAndWhatHadNoPlaceOnIt(): void
    {
        $model = new ScriptedConventionsModel([new Reading(true, self::JACK_30, ['et souris', 'a sentence of the model’s own'])]);

        $answer = self::ask(self::client($model), self::ASKED);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        // the card in the engine's own shape, and of the rest only the player's words
        self::assertSame(['version' => 1, 'card' => ['open' => 70, 'jack' => 30, 'nine' => 10, 'threeTrumps' => 10, 'belote' => 20, 'ace' => 10, 'aces' => 'atOnce'], 'unplaced' => ['et souris']], $answer);
        self::assertSame([[self::ASKED['sentence'], Lang::Fr]], $model->asked);
    }

    public function testASentenceThatDescribesNoCardIsSaidSo(): void
    {
        $client = self::client(new ScriptedConventionsModel([new Reading(false, ConventionCard::READY['classic'], []), new Reading(true, ['jack' => 500] + self::JACK_30, [])]));

        foreach (['il fait beau', 'monte de 500 avec le valet'] as $sentence) {
            $answer = self::ask($client, ['sentence' => $sentence, 'lang' => 'fr']);

            self::assertResponseStatusCodeSame(422);
            self::assertSame(['error' => ['code' => 'not_understood', 'message' => 'The sentence describes no conventions card.']], $answer);
        }
    }

    /** @param array<mixed>|string $body */
    #[DataProvider('bodiesThatAreNoSentence')]
    public function testABodyThatIsNotASentenceIsABadRequestSaidInWordsAndTheModelIsNotAsked(array|string $body, string $message): void
    {
        $model = new ScriptedConventionsModel([]);

        $answer = self::ask(self::client($model), $body);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => ['code' => 'bad_request', 'message' => $message]], $answer);
        self::assertSame([], $model->asked);
    }

    /** @return iterable<string, array{array<mixed>|string, string}> */
    public static function bodiesThatAreNoSentence(): iterable
    {
        $shape = 'The body holds a sentence and a language (fr, en or ar).';
        yield 'not JSON' => ['monte de 30', 'The body is not JSON, or holds more than a sentence and a language.'];
        yield 'deeper than a sentence and a language' => [['sentence' => ['a' => ['b' => 1]], 'lang' => 'fr'], 'The body is not JSON, or holds more than a sentence and a language.'];
        yield 'a list' => ['[]', $shape];
        yield 'no sentence' => [['lang' => 'fr'], $shape];
        yield 'a sentence that is no text' => [['sentence' => 30, 'lang' => 'fr'], $shape];
        yield 'no language' => [['sentence' => 'monte de 30'], $shape];
        yield 'a language the game does not speak' => [['sentence' => 'monte de 30', 'lang' => 'de'], $shape];
        yield 'an empty sentence' => [['sentence' => '  ', 'lang' => 'fr'], 'The sentence is 1 to 300 characters.'];
        yield 'a sentence too long' => [['sentence' => str_repeat('a', 301), 'lang' => 'fr'], 'The sentence is 1 to 300 characters.'];
        yield 'a body too large' => [['sentence' => str_repeat('a', 5000), 'lang' => 'fr'], 'The body is larger than 4096 bytes.'];
    }

    public function testWithoutAKeySentencesAreNotReadAndTheAnswerSaysHowToSwitchThemOn(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY']);
        $model = new ScriptedConventionsModel([]);

        $answer = self::ask(self::client($model), self::ASKED);

        self::assertResponseStatusCodeSame(503);
        self::assertSame(['error' => ['code' => 'ai_off', 'message' => 'Sentences are not read: set ANTHROPIC_API_KEY in .env and start again. The ready cards need no key.']], $answer);
        self::assertSame([], $model->asked);
    }

    public function testSettingsThatCannotWorkAreSaidWithoutTheKey(): void
    {
        $_SERVER['KABBOUT_AI_MODEL'] = ' ';

        $answer = self::ask(self::client(new ScriptedConventionsModel([])), self::ASKED);

        self::assertResponseStatusCodeSame(503);
        self::assertIsArray($answer['error']);
        self::assertSame('invalid_ai_settings', $answer['error']['code']);
        self::assertStringNotContainsString('sk-ant-a-secret-key', (string) json_encode($answer));
    }

    public function testAModelThatGivesNoReadingIsAnsweredWithoutItsWords(): void
    {
        $answer = self::ask(self::client(new ScriptedConventionsModel([])), self::ASKED);

        self::assertResponseStatusCodeSame(503);
        self::assertSame(['error' => ['code' => 'model_unavailable', 'message' => 'The sentence could not be read right now. The ready cards need no model.']], $answer);
    }

    public function testSentencesAreCountedWithTheCoachsQuestionsAVisitorIsStoppedPastTheLimit(): void
    {
        $_SERVER['KABBOUT_COACH_LIMIT'] = '2';
        $reading = new Reading(true, self::JACK_30, []);
        $client = self::client(new ScriptedConventionsModel([$reading, $reading, $reading, $reading]));

        self::ask($client, self::ASKED);
        self::assertResponseIsSuccessful();
        // a bad request costs nothing
        self::ask($client, ['lang' => 'fr']);
        self::assertResponseStatusCodeSame(400);
        self::ask($client, self::ASKED);
        self::assertResponseIsSuccessful();

        $answer = self::ask($client, self::ASKED);

        self::assertResponseStatusCodeSame(429);
        self::assertSame(['error' => ['code' => 'too_many_requests', 'message' => 'Too Many Requests']], $answer);
        self::assertGreaterThanOrEqual(1, (int) $client->getResponse()->headers->get('Retry-After'));
        // another visitor is not the one who asked too much
        self::ask($client, self::ASKED, self::someone());
        self::assertResponseIsSuccessful();
    }

    public function testTheDayHasSoManyQuestionsForAllVisitorsTogether(): void
    {
        // the count of the day is one for everybody, the coach's questions included: this test takes what is left of it
        $_SERVER['KABBOUT_COACH_DAILY'] = '1';
        $reading = new Reading(true, self::JACK_30, []);
        $model = new ScriptedConventionsModel([$reading, $reading]);
        $client = self::client($model);
        self::ask($client, self::ASKED);

        // another visitor, the same day
        $answer = self::ask($client, self::ASKED, self::someone());

        self::assertResponseStatusCodeSame(429);
        self::assertSame(['error' => ['code' => 'daily_quota', 'message' => 'The day\'s questions are spent.']], $answer);
        self::assertLessThanOrEqual(1, \count($model->asked));
    }

    public function testAPageOfAnotherSiteCannotAskForTheVisitor(): void
    {
        $model = new ScriptedConventionsModel([new Reading(true, self::JACK_30, [])]);
        $client = self::client($model);

        foreach ([['HTTP_SEC_FETCH_SITE' => 'cross-site'], ['HTTP_SEC_FETCH_SITE' => '', 'HTTP_ORIGIN' => 'https://evil.example'], ['HTTP_CSRF_TOKEN' => '']] as $says) {
            $answer = self::ask($client, self::ASKED, headers: $says);

            self::assertResponseStatusCodeSame(401);
            self::assertSame(['error' => ['code' => 'unauthorized', 'message' => 'Unauthorized']], $answer);
        }
        self::assertSame([], $model->asked);
    }

    public function testSentencesAreOnlyTakenByPost(): void
    {
        $client = self::client(new ScriptedConventionsModel([]));
        $client->request('GET', '/api/conventions/interpret');

        self::assertResponseStatusCodeSame(405);
    }

    private static function someone(): string
    {
        return '2001:db8:'.bin2hex(random_bytes(2)).':'.bin2hex(random_bytes(2)).'::1';
    }

    private static function client(ConventionsModel $model): KernelBrowser
    {
        $client = self::createClient();
        // one kernel for the whole test: what is put in the container and what the limiter counted stay
        $client->disableReboot();
        self::getContainer()->set(ConventionsModel::class, $model);

        return $client;
    }

    /**
     * @param array<mixed>|string   $body
     * @param array<string, string> $headers
     *
     * @return array<mixed>
     */
    private static function ask(KernelBrowser $client, array|string $body, ?string $from = null, array $headers = []): array
    {
        $client->request('POST', '/api/conventions/interpret', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $from ?? self::$visitor, ...self::FROM_THE_PAGE, ...$headers], content: \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);

        return $answer;
    }
}
