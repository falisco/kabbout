<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Domain;

use Kabbout\Assistant\Domain\AiSettings;
use Kabbout\Assistant\Domain\Feature;
use Kabbout\Assistant\Domain\FeatureState;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Domain\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiSettingsTest extends TestCase
{
    public function testSettingsThatExistAreComplete(): void
    {
        $settings = new AiSettings(Platform::HuggingFace, 'a-key', 'openai/gpt-oss-120b', 'groq');

        self::assertSame('a-key', $settings->apiKey());
        self::assertSame('openai/gpt-oss-120b', $settings->model);
        self::assertSame('groq', $settings->provider);
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function holes(): iterable
    {
        yield 'no key' => ['', 'a-model', null];
        yield 'a key of spaces' => ['  ', 'a-model', null];
        yield 'no model' => ['a-key', '', null];
        yield 'an empty provider' => ['a-key', 'a-model', ''];
        yield 'a provider of spaces' => ['a-key', 'a-model', ' '];
    }

    #[DataProvider('holes')]
    public function testSettingsWithAHoleCannotExist(string $key, string $model, ?string $provider): void
    {
        $this->expectException(InvalidAiSettings::class);
        new AiSettings(Platform::HuggingFace, $key, $model, $provider);
    }

    public function testADumpOfTheSettingsHidesTheKey(): void
    {
        $dump = print_r(new AiSettings(Platform::HuggingFace, 'a-secret-key', 'a-model'), true);

        self::assertStringNotContainsString('a-secret-key', $dump);
        self::assertStringContainsString('a-model', $dump);
    }

    public function testSettingsAreNotSerialised(): void
    {
        $this->expectException(\LogicException::class);
        serialize(new AiSettings(Platform::HuggingFace, 'a-secret-key', 'a-model'));
    }

    public function testAFeatureIsPlannedUntilItShipsThenFollowsTheSettings(): void
    {
        self::assertSame(FeatureState::Planned, FeatureState::of(false, false));
        self::assertSame(FeatureState::Planned, FeatureState::of(false, true));
        self::assertSame(FeatureState::Off, FeatureState::of(true, false));
        self::assertSame(FeatureState::On, FeatureState::of(true, true));
    }

    public function testNoFeatureHasShippedYetSoAKeySwitchesNothingOn(): void
    {
        $settings = new AiSettings(Platform::HuggingFace, 'a-key', 'a-model');
        foreach (Feature::cases() as $feature) {
            self::assertSame(FeatureState::Planned, $feature->stateWith($settings), $feature->value);
            self::assertSame(FeatureState::Planned, $feature->stateWith(null), $feature->value);
        }
    }
}
