<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\AiSettingsDsn;
use Kabbout\Assistant\Infrastructure\AiSettingsEnvVarProcessor;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class AiSettingsDsnTest extends TestCase
{
    private const string DSN = 'huggingface://hf_k%40y@groq/openai/gpt-oss-120b';

    public function testNothingWrittenMeansTheAiIsOff(): void
    {
        self::assertNull(AiSettingsDsn::parse(''));
        self::assertNull(AiSettingsDsn::parse("  \n"));
        self::assertNull((new EnvAiConfiguration(''))->settings());
    }

    public function testTheAddressGivesThePlatformTheKeyTheProviderAndTheModel(): void
    {
        $settings = (new EnvAiConfiguration(self::DSN))->settings();

        self::assertNotNull($settings);
        self::assertSame('hf_k@y', $settings->apiKey());
        self::assertSame('groq', $settings->provider);
        self::assertSame('openai/gpt-oss-120b', $settings->model);
    }

    public function testTheProviderAutoLeavesTheChoiceToThePlatform(): void
    {
        self::assertNull(AiSettingsDsn::parse('HuggingFace://hf_key@auto/a-model')?->provider);
    }

    /** @return iterable<string, array{string, string}> */
    public static function wrong(): iterable
    {
        yield 'an unknown platform' => ['nope://a-secret-key@auto/a-model', 'platform "nope" is not known'];
        yield 'a key alone' => ['a-secret-key', 'not written as'];
        yield 'no key' => ['huggingface://auto/a-model', 'not written as'];
        yield 'no model' => ['huggingface://a-secret-key@auto/', 'not written as'];
        yield 'no provider' => ['huggingface://a-secret-key@/a-model', 'not written as'];
    }

    #[DataProvider('wrong')]
    public function testSettingsThatCannotWorkAreRefusedWithoutRepeatingTheKey(string $dsn, string $why): void
    {
        try {
            AiSettingsDsn::parse($dsn);
            self::fail('The settings were accepted.');
        } catch (InvalidAiSettings $e) {
            self::assertStringContainsString($why, $e->getMessage());
            self::assertStringNotContainsString('a-secret-key', $e->getMessage());
        }
    }

    public function testABundleCanTakeOnePartOfTheVariable(): void
    {
        $processor = new AiSettingsEnvVarProcessor();
        $on = static fn (string $name): string => 'KABBOUT_AI' === $name ? self::DSN : '';
        $off = static fn (): string => '';

        self::assertSame(['kabbout_ai' => 'string'], AiSettingsEnvVarProcessor::getProvidedTypes());
        self::assertSame('hf_k@y', $processor->getEnv('kabbout_ai', 'api_key:KABBOUT_AI', $on));
        self::assertSame('groq', $processor->getEnv('kabbout_ai', 'provider:KABBOUT_AI', $on));
        self::assertSame('openai/gpt-oss-120b', $processor->getEnv('kabbout_ai', 'model:KABBOUT_AI', $on));
        self::assertSame('', $processor->getEnv('kabbout_ai', 'api_key:KABBOUT_AI', $off));
        self::assertSame('', $processor->getEnv('kabbout_ai', 'model:KABBOUT_AI', $off));
        self::assertNull($processor->getEnv('kabbout_ai', 'provider:KABBOUT_AI', $off));

        $this->expectException(RuntimeException::class);
        $processor->getEnv('kabbout_ai', 'colour:KABBOUT_AI', $on);
    }
}
