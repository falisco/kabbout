<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Api\Domain\Envelope;
use Kabbout\Assistant\Application\Capabilities\GetCapabilities;
use Kabbout\Assistant\Domain\Settings\FeatureState;
use Kabbout\Assistant\Domain\Settings\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\Settings\EnvAiConfiguration;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/capabilities: what a client asks before it shows anything about the AI.
 * The page reads `state` and writes its own sentences in its three languages; `hint` is the same thing said
 * once in English for whoever reads the answer with no page around it.
 */
final readonly class CapabilitiesController
{
    public function __construct(private GetCapabilities $getCapabilities)
    {
    }

    #[Route('/api/capabilities', name: 'capabilities', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $capabilities = ($this->getCapabilities)();
        } catch (InvalidAiSettings $e) {
            // the start-up check stops this before a request comes; a server started without it still answers in JSON
            return new JsonResponse(Envelope::error('invalid_ai_settings', $e->getMessage()), 503);
        }
        $features = [];
        foreach ($capabilities->features as $name => $state) {
            $features[$name] = ['state' => $state->value, 'hint' => self::hint($state)];
        }

        return new JsonResponse(Envelope::answer([
            'ai' => [
                'configured' => $capabilities->configured(),
                'platform' => $capabilities->platform?->value,
                'model' => $capabilities->model,
                'variable' => EnvAiConfiguration::VARIABLE,
            ],
            'features' => $features,
        ]));
    }

    private static function hint(FeatureState $state): ?string
    {
        return match ($state) {
            FeatureState::Planned => \sprintf('Arrives in a later version; it will be switched on with %s.', EnvAiConfiguration::VARIABLE),
            FeatureState::Off => \sprintf('Off: set %s in .env and start again.', EnvAiConfiguration::VARIABLE),
            FeatureState::On => null,
        };
    }
}
