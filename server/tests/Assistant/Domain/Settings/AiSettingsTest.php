<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Domain\Settings;

use Kabbout\Assistant\Domain\Settings\AiSettings;
use Kabbout\Assistant\Domain\Settings\Feature;
use Kabbout\Assistant\Domain\Settings\FeatureState;
use Kabbout\Assistant\Domain\Settings\InvalidAiSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiSettingsTest extends TestCase
{
    public function testSettingsThatExistAreComplete(): void
    {
        $settings = new AiSettings('a-key', 'claude-opus-5-5');

        self::assertSame('a-key', $settings->apiKey());
        self::assertSame('claude-opus-5-5', $settings->model);
    }

    /** @return iterable<string, array{string, string}> */
    public static function holes(): iterable
    {
        yield 'no key' => ['', 'a-model'];
        yield 'a key of spaces' => ['  ', 'a-model'];
        yield 'no model' => ['a-key', ''];
        yield 'a model of spaces' => ['a-key', ' '];
    }

    #[DataProvider('holes')]
    public function testSettingsWithAHoleCannotExist(string $key, string $model): void
    {
        $this->expectException(InvalidAiSettings::class);
        new AiSettings($key, $model);
    }

    public function testADumpOfTheSettingsHidesTheKey(): void
    {
        $dump = print_r(new AiSettings('a-secret-key', 'a-model'), true);

        self::assertStringNotContainsString('a-secret-key', $dump);
        self::assertStringContainsString('a-model', $dump);
    }

    public function testSettingsAreNotSerialised(): void
    {
        $this->expectException(\LogicException::class);
        serialize(new AiSettings('a-secret-key', 'a-model'));
    }

    public function testAFeatureIsPlannedUntilItShipsThenFollowsTheSettings(): void
    {
        self::assertSame(FeatureState::Planned, FeatureState::of(false, false));
        self::assertSame(FeatureState::Planned, FeatureState::of(false, true));
        self::assertSame(FeatureState::Off, FeatureState::of(true, false));
        self::assertSame(FeatureState::On, FeatureState::of(true, true));
    }

    public function testAKeySwitchesOnTheFeaturesThatHaveShippedAndOnlyThose(): void
    {
        $settings = new AiSettings('a-key', 'a-model');
        self::assertSame(FeatureState::On, Feature::Coach->stateWith($settings));
        self::assertSame(FeatureState::Off, Feature::Coach->stateWith(null));
        self::assertSame(FeatureState::On, Feature::Conventions->stateWith($settings));
        self::assertSame(FeatureState::Off, Feature::Conventions->stateWith(null));
        foreach ([Feature::Referee, Feature::AgentSeat] as $feature) {
            self::assertSame(FeatureState::Planned, $feature->stateWith($settings), $feature->value);
            self::assertSame(FeatureState::Planned, $feature->stateWith(null), $feature->value);
        }
    }
}
