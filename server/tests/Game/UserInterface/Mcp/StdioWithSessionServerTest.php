<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;

/** The revision before the newest, where a client opens a session first. Who is asking does not depend on it. */
final class StdioWithSessionServerTest extends McpServerTestCase
{
    use SpeaksOverStdio;

    protected static function revision(): ProtocolVersion
    {
        return ProtocolVersion::V2025_11_25;
    }
}
