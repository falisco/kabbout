<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Mcp\Client;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;

/**
 * The MCP server as a client outside it meets it: `bin/console mcp:server` started as a process and
 * spoken to over its standard input and output, by the SDK's own client. One process is one guest.
 */
trait SpeaksOverStdio
{
    /** every process started here is counted against the one address that is none: the tests together would be refused */
    private const array NO_PACE = ['KABBOUT_TABLES_LIMIT' => '100000', 'KABBOUT_TABLES_OPENED' => '100000', 'KABBOUT_GUESTS_LIMIT' => '100000'];

    /** @var list<Client> */
    private array $clients = [];

    abstract protected static function revision(): ProtocolVersion;

    protected function tearDown(): void
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }
        $this->clients = [];
        parent::tearDown();
    }

    public function testItOffersTheTableTheReviewAndTheRules(): void
    {
        $client = $this->client();

        $offered = array_map(static fn (Tool $tool): string => $tool->name, $client->listTools()->tools);
        sort($offered);
        $expected = self::TOOLS;
        sort($expected);
        self::assertSame($expected, $offered);

        self::assertStringContainsString('J 9 A T K Q 8 7', (string) $client->getInstructions());
        self::assertStringContainsString('legal_moves', json_encode($client->readResource('kabbout://rules')->contents, \JSON_THROW_ON_ERROR));
    }

    protected function player(): \Closure
    {
        $client = $this->client();

        return static function (string $tool, array $arguments = []) use ($client): array {
            /** @var array<string, mixed> $arguments */
            $result = $client->callTool($tool, $arguments);
            $first = $result->content[0] ?? null;
            /** @var array<string, mixed> $answer */
            $answer = \is_array($result->structuredContent) ? $result->structuredContent : [];

            return ['refused' => $result->isError, 'said' => $first instanceof TextContent && \is_string($first->text) ? $first->text : '', 'answer' => $answer];
        };
    }

    private function client(): Client
    {
        $client = Client::builder()->setClientInfo('kabbout-tests', '1.0.0')->setProtocolVersion(static::revision())->setRequestTimeout(60)->build();
        $client->connect(new StdioTransport(\PHP_BINARY, ['bin/console', 'mcp:server'], \dirname(__DIR__, 4), [...getenv(), 'APP_ENV' => 'test', 'APP_DEBUG' => '0', ...self::NO_PACE]));

        return $this->clients[] = $client;
    }
}
