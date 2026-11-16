<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Domain\AiSettings;
use Symfony\AI\Platform\PlatformInterface;

/** Builds the platform the settings name, when a question is asked: the settings are only known at run time. */
interface AiPlatformFactory
{
    public function create(AiSettings $settings): PlatformInterface;
}
