<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\AiSettingsDsn;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

    public function testADumpOfTheConfigurationHidesTheVariable(): void
    {
        self::assertStringNotContainsString('hf_k', print_r(new EnvAiConfiguration(self::DSN), true));
    }

    public function testAModelMayHoldSlashesColonsAndDots(): void
    {
        self::assertSame('org/model-v1.5:fast', AiSettingsDsn::parse('huggingface://k@fal-ai/org/model-v1.5:fast')?->model);
    }

    public function testTheProviderAutoLeavesTheChoiceToThePlatform(): void
    {
        self::assertNull(AiSettingsDsn::parse('HuggingFace://hf_key@auto/a-model')?->provider);
    }

    /** @return iterable<string, array{string, string}> */
    public static function wrong(): iterable
    {
        yield 'an unknown platform' => ['nope://a-secret-key@auto/a-model', 'platform is not known'];
        yield 'the key where the platform goes' => ['asecretkey://huggingface@auto/a-model', 'platform is not known'];
        yield 'a key with an @ left as it is' => ['huggingface://a-secret-key@tail@auto/a-model', 'not written as'];
        yield 'a provider that is not a name' => ['huggingface://a-secret-key@gr oq/a-model', 'not written as'];
        yield 'a model with a question mark' => ['huggingface://a-secret-key@auto/a-model?x=1', 'not written as'];
        yield 'a key that decodes to a line break' => ['huggingface://a-secret-key%0A@auto/a-model', 'control character'];
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
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }
}
