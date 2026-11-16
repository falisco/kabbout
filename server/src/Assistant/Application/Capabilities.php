<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application;

use Kabbout\Assistant\Domain\Feature;
use Kabbout\Assistant\Domain\FeatureState;
use Kabbout\Assistant\Domain\Platform;

/** What this server can do with the AI right now. It holds nothing secret: it may be sent to anyone. */
final readonly class Capabilities
{
    /** @param array<value-of<Feature>, FeatureState> $features every feature, by its name */
    public function __construct(public ?Platform $platform, public ?string $model, public array $features)
    {
    }

    public function configured(): bool
    {
        return null !== $this->platform;
    }
}
