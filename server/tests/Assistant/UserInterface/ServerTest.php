<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\UserInterface;

use Kabbout\Assistant\Domain\Feature;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Infrastructure\Bridge\BridgeEngine;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** The server as it is put together: its container, its one route and its start-up check. */
final class ServerTest extends WebTestCase
{
    private const string KEY = 'hf_a-secret-key';

    protected function tearDown(): void
    {
        unset($_SERVER['KABBOUT_AI']);
        parent::tearDown();
    }

    public function testWithNoKeyTheAnswerSaysTheAiIsOffAndWhatEachFeatureWaitsFor(): void
    {
        $answer = self::capabilities();

        self::assertSame(1, $answer['version']);
        self::assertSame(['configured' => false, 'platform' => null, 'model' => null, 'variable' => 'KABBOUT_AI'], $answer['ai']);
        self::assertIsArray($answer['features']);
        self::assertSame(array_column(Feature::cases(), 'value'), array_keys($answer['features']));
        foreach ($answer['features'] as $feature) {
            self::assertIsArray($feature);
            self::assertSame('planned', $feature['state']);
            self::assertIsString($feature['hint']);
            self::assertStringContainsString('KABBOUT_AI', $feature['hint']);
        }
    }

    public function testWithAKeyTheAnswerNamesThePlatformAndTheModelAndNeverTheKey(): void
    {
        $_SERVER['KABBOUT_AI'] = 'huggingface://'.self::KEY.'@auto/openai/gpt-oss-120b';
        $client = self::createClient();
        $client->request('GET', '/api/capabilities');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString(self::KEY, $body);
        self::assertStringContainsString('"configured":true,"platform":"huggingface","model":"openai\/gpt-oss-120b"', $body);
    }

    public function testSettingsThatCannotWorkAreAnsweredInJsonWithoutTheKey(): void
    {
        $_SERVER['KABBOUT_AI'] = 'nope://'.self::KEY.'@auto/a-model';
        $client = self::createClient();
        $client->request('GET', '/api/capabilities');

        self::assertResponseStatusCodeSame(503);
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('"code":"invalid_ai_settings"', $body);
        self::assertStringNotContainsString(self::KEY, $body);
        self::assertStringNotContainsString('nope', $body);
    }

    public function testTheCheckPassesWithTheAiOffAndWithGoodSettings(): void
    {
        self::assertStringContainsString('AI: off', self::check(0));

        self::ensureKernelShutdown();
        $_SERVER['KABBOUT_AI'] = 'huggingface://'.self::KEY.'@auto/a-model';
        $output = self::check(0);
        self::assertStringContainsString('AI: on, huggingface with a-model, run by the provider the platform picks.', $output);
        self::assertStringNotContainsString(self::KEY, $output);
    }

    public function testTheCheckStopsTheStartOnAnUnknownPlatformWithoutRepeatingTheKey(): void
    {
        $_SERVER['KABBOUT_AI'] = 'nope://'.self::KEY.'@auto/a-model';
        $output = self::check(1);

        self::assertStringContainsString('Kabbout cannot start: The AI platform is not known. Known: huggingface.', $output);
        self::assertStringNotContainsString('nope', $output);
        self::assertStringNotContainsString(self::KEY, $output);
    }

    public function testThePortsOfTheDomainsGetTheirAdapters(): void
    {
        self::assertInstanceOf(BridgeEngine::class, self::getContainer()->get(Engine::class));
    }

    /** @return array<array-key, mixed> */
    private static function capabilities(): array
    {
        $client = self::createClient();
        $client->request('GET', '/api/capabilities');
        self::assertResponseIsSuccessful();
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);

        return $answer;
    }

    private static function check(int $expected): string
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('kabbout:check'));
        self::assertSame($expected, $tester->execute([]));

        return $tester->getDisplay();
    }
}
