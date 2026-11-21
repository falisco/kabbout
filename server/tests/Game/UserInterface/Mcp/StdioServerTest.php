<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;

/** The newest revision of the protocol, which keeps no session: who is asking is the process. */
final class StdioServerTest extends McpServerTestCase
{
    use SpeaksOverStdio;

    protected static function revision(): ProtocolVersion
    {
        return ProtocolVersion::V2026_07_28;
    }
}
