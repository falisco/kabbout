<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\AiSettings;

/** The AI settings as the environment gives them, in the one variable AiSettingsDsn reads. */
final readonly class EnvAiConfiguration implements AiConfiguration
{
    public function __construct(#[\SensitiveParameter] private string $dsn)
    {
    }

    public function settings(): ?AiSettings
    {
        return AiSettingsDsn::parse($this->dsn);
    }
}
