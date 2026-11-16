<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Lets the configuration of a bundle take one part of the AI variable, read by the same parser as the rest
 * of the server: `%env(kabbout_ai:api_key:KABBOUT_AI)%`. With the AI off, a part is empty instead of an error,
 * so that a container whose AI services are never used still builds.
 */
final class AiSettingsEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): ?string
    {
        [$part, $variable] = explode(':', $name, 2) + [1 => ''];
        $dsn = $getEnv($variable);
        $settings = AiSettingsDsn::parse(\is_string($dsn) ? $dsn : '');

        return match ($part) {
            'api_key' => $settings?->apiKey() ?? '',
            'model' => $settings->model ?? '',
            'provider' => $settings?->provider,
            default => throw new RuntimeException(\sprintf('The AI settings have no "%s".', $part)),
        };
    }

    /** @return array<string, string> */
    public static function getProvidedTypes(): array
    {
        return ['kabbout_ai' => 'string'];
    }
}
