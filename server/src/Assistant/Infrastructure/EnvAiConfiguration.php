<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\AiSettings;

/**
 * The AI settings as the environment gives them: the two variables the Symfony AI bundle is configured
 * with (config/packages/ai.yaml). A key switches the AI on; without one it is off.
 */
final readonly class EnvAiConfiguration implements AiConfiguration
{
    /** the variables, as .env.example, compose.yaml and the configuration name them */
    public const string VARIABLE = 'ANTHROPIC_API_KEY';
    public const string MODEL_VARIABLE = 'KABBOUT_AI_MODEL';

    public function __construct(#[\SensitiveParameter] private string $apiKey, private string $model)
    {
    }

    public function settings(): ?AiSettings
    {
        return '' === trim($this->apiKey) ? null : new AiSettings(trim($this->apiKey), trim($this->model));
    }

    /** @return array{apiKey: string, model: string} a dump shows nothing of the key */
    public function __debugInfo(): array
    {
        return ['apiKey' => '(hidden)', 'model' => $this->model];
    }
}
