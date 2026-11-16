<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application;

use Kabbout\Assistant\Application\GetCapabilities;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\AiSettings;
use Kabbout\Assistant\Domain\Feature;
use Kabbout\Assistant\Domain\Platform;
use PHPUnit\Framework\TestCase;

final class GetCapabilitiesTest extends TestCase
{
    public function testWithTheAiOffEveryFeatureIsStillListed(): void
    {
        $capabilities = (new GetCapabilities(self::configuredWith(null)))();

        self::assertFalse($capabilities->configured());
        self::assertNull($capabilities->model);
        self::assertSame(array_column(Feature::cases(), 'value'), array_keys($capabilities->features));
    }

    public function testWithTheAiOnTheAnswerNamesThePlatformAndTheModel(): void
    {
        $capabilities = (new GetCapabilities(self::configuredWith(new AiSettings('a-key', 'a-model'))))();

        self::assertTrue($capabilities->configured());
        self::assertSame(Platform::Anthropic, $capabilities->platform);
        self::assertSame('a-model', $capabilities->model);
    }

    private static function configuredWith(?AiSettings $settings): AiConfiguration
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
}
