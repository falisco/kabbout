<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge\Transport;

use Kabbout\Game\Domain\Exception\EngineUnavailable;

/** How a call reaches the bridge and its answer comes back. What the call means is BridgeEngine's business. */
interface BridgeTransport
{
    /**
     * @param array<string, mixed> $body
     *
     * @throws EngineUnavailable when no answer could be had
     */
    public function post(string $path, array $body): BridgeResponse;
}
