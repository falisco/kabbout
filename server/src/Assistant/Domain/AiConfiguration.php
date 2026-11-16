<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/** How this server was set up for the AI, wherever that is written. */
interface AiConfiguration
{
    /**
     * @return ?AiSettings null when the AI is off
     *
     * @throws InvalidAiSettings when it was switched on with settings that cannot work
     */
    public function settings(): ?AiSettings;
}
