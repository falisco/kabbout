<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/**
 * What switches the AI on: a platform, a key for it and the model to ask. Settings that exist are complete;
 * the AI being off is the absence of settings, not settings with holes.
 */
final readonly class AiSettings
{
    /** @param ?string $provider who runs the model behind the platform; the platform picks one when null */
    public function __construct(
        public Platform $platform,
        #[\SensitiveParameter] private string $apiKey,
        public string $model,
        public ?string $provider = null,
    ) {
        if ('' === trim($apiKey)) {
            throw new InvalidAiSettings('The AI settings hold no key.');
        }
        if ('' === trim($model)) {
            throw new InvalidAiSettings('The AI settings name no model.');
        }
        if ('' === $provider) {
            throw new InvalidAiSettings('The AI settings name an empty provider.');
        }
    }

    /** For the platform only. The key is in no answer, no log and no dump. */
    public function apiKey(): string
    {
        return $this->apiKey;
    }

    /** @return array{platform: string, model: string, provider: ?string, apiKey: string} */
    public function __debugInfo(): array
    {
        return ['platform' => $this->platform->value, 'model' => $this->model, 'provider' => $this->provider, 'apiKey' => '(hidden)'];
    }
}
