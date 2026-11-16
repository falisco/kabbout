<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Domain\AiSettings;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Hugging Face's inference router, with the key, the provider and the model of the settings. */
final readonly class HuggingFacePlatformFactory implements AiPlatformFactory
{
    /** seconds without a byte from the platform, then seconds for a whole call: a player is waiting */
    private const float IDLE_TIMEOUT = 20.0;
    private const float CALL_TIMEOUT = 45.0;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function create(AiSettings $settings): PlatformInterface
    {
        $catalog = new ModelCatalog();
        if (!isset($catalog->getModels()[$settings->model])) {
            // the bundled list is a snapshot: a chat model published since is taken at the word of the settings
            $catalog = new ModelCatalog([$settings->model => [
                'class' => Completions::class,
                'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::TOOL_CALLING],
            ]]);
        }

        return Factory::createPlatform(
            $settings->apiKey(),
            $settings->provider,
            $this->httpClient->withOptions(['timeout' => self::IDLE_TIMEOUT, 'max_duration' => self::CALL_TIMEOUT]),
            $catalog,
        );
    }
}
