<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\AiSettings;

/** The AI settings as the environment gives them, in the one variable AiSettingsDsn reads. */
final readonly class EnvAiConfiguration implements AiConfiguration
{
    /** the variable, as .env.example, compose.yaml and config/services.yaml name it */
    public const string VARIABLE = 'KABBOUT_AI';

    public function __construct(#[\SensitiveParameter] private string $dsn)
    {
    }

    public function settings(): ?AiSettings
    {
        return AiSettingsDsn::parse($this->dsn);
    }

    /** @return array{dsn: string} the value holds the key: a dump shows none of it */
    public function __debugInfo(): array
    {
        return ['dsn' => '(hidden)'];
    }
}
