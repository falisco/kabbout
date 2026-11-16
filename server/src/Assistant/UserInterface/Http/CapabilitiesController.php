<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Assistant\Application\GetCapabilities;
use Kabbout\Assistant\Domain\FeatureState;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/capabilities: what a client asks before it shows anything about the AI.
 * The page reads `state` and writes its own sentences in its three languages; `hint` is the same thing said
 * once in English for whoever reads the answer with no page around it.
 */
final readonly class CapabilitiesController
{
    /** the variable that switches the AI on, as .env.example and the README name it */
    public const string VARIABLE = 'KABBOUT_AI';
    private const int VERSION = 1;

    public function __construct(private GetCapabilities $getCapabilities)
    {
    }

    #[Route('/api/capabilities', name: 'capabilities', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $capabilities = ($this->getCapabilities)();
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
                    'variable' => self::VARIABLE,
                ],
                'features' => $features,
            ],
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    private static function hint(FeatureState $state): ?string
    {
        return match ($state) {
            FeatureState::Planned => \sprintf('Arrives in a later version; it will be switched on with %s.', self::VARIABLE),
            FeatureState::Off => \sprintf('Off: set %s in .env and start again.', self::VARIABLE),
            FeatureState::On => null,
        };
    }
}
