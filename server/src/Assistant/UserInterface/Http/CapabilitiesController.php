<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Assistant\Application\GetCapabilities;
use Kabbout\Assistant\Domain\FeatureState;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/capabilities: what a client asks before it shows anything about the AI.
 * The page reads `state` and writes its own sentences in its three languages; `hint` is the same thing said
 * once in English for whoever reads the answer with no page around it.
 */
final readonly class CapabilitiesController
{
    private const int VERSION = 1;

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
            return new JsonResponse(['error' => ['code' => 'invalid_ai_settings', 'message' => $e->getMessage()]], 503, ['Cache-Control' => 'no-store']);
        }
        $features = [];
        foreach ($capabilities->features as $name => $state) {
            $features[$name] = ['state' => $state->value, 'hint' => self::hint($state)];
        }

        return new JsonResponse(
            [
                'version' => self::VERSION,
                'ai' => [
                    'configured' => $capabilities->configured(),
                    'platform' => $capabilities->platform?->value,
                    'model' => $capabilities->model,
                    'variable' => EnvAiConfiguration::VARIABLE,
                ],
                'features' => $features,
            ],
            headers: ['Cache-Control' => 'no-store'],
        );
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
