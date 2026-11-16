<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/**
 * What switches the AI on: a key for the platform and the model to ask. Settings that exist are complete;
 * the AI being off is the absence of settings, not settings with holes.
 */
final readonly class AiSettings
{
    public Platform $platform;

    public function __construct(#[\SensitiveParameter] private string $apiKey, public string $model)
    {
        if ('' === trim($apiKey)) {
            throw new InvalidAiSettings('The AI settings hold no key.');
        }
        if ('' === trim($model)) {
            throw new InvalidAiSettings('The AI settings name no model.');
        }
        $this->platform = Platform::Anthropic;
    }

    /** For the platform only. The key is in no answer, no log and no dump. */
    public function apiKey(): string
    {
        return $this->apiKey;
    }

    /** @return never settings hold a key: they are not written anywhere */
    public function __serialize(): array
    {
        throw new \LogicException('AI settings are not serialised: they hold a key.');
    }

    /** @return array{platform: string, model: string, apiKey: string} */
    public function __debugInfo(): array
    {
        return ['platform' => $this->platform->value, 'model' => $this->model, 'apiKey' => '(hidden)'];
    }
}
