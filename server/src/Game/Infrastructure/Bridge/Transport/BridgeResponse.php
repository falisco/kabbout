<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge\Transport;

/** What the bridge answered to one call: its HTTP status and its JSON, decoded. */
final readonly class BridgeResponse
{
    public function __construct(public int $status, public mixed $body)
    {
    }
}
