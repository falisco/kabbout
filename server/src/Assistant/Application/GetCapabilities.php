<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application;

use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\Feature;
use Kabbout\Assistant\Domain\InvalidAiSettings;

/** The question a client asks first: which AI features are there, and which could be. */
final readonly class GetCapabilities
{
    public function __construct(private AiConfiguration $configuration)
    {
    }

    /** @throws InvalidAiSettings */
    public function __invoke(): Capabilities
    {
        $settings = $this->configuration->settings();
        $features = [];
        foreach (Feature::cases() as $feature) {
            $features[$feature->value] = $feature->stateWith($settings);
        }

        return new Capabilities($settings?->platform, $settings?->model, $features);
    }
}
