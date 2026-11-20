<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Tests\Support\ListedLog;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The MCP server over HTTP, under /mcp: who may come in and whose a table is. A client is a guest who
 * sends their secret in the `Authorization` header; Symfony Security recognises it, and the tools ask
 * who is asking the way every door does.
 */
final class HttpServerTest extends McpServerTestCase
{
    private ?KernelBrowser $browser = null;

    protected function tearDown(): void
    {
        $this->browser = null;
        parent::tearDown();
    }

    protected function player(): \Closure
    {
        $browser = $this->browser ??= self::createClient();
        $secret = self::guest();

        return static function (string $tool, array $arguments = []) use ($browser, $secret): array {
            $result = self::call($browser, $secret, 'tools/call', ['name' => $tool, 'arguments' => [] === $arguments ? new \stdClass() : $arguments]);
            self::assertResponseIsSuccessful();

            $said = $result['content'] ?? null;
            $said = \is_array($said) && \is_array($said[0] ?? null) && \is_string($said[0]['text'] ?? null) ? $said[0]['text'] : '';
            /** @var array<string, mixed> $answer */
            $answer = \is_array($result['structuredContent'] ?? null) ? $result['structuredContent'] : [];

            return ['refused' => true === ($result['isError'] ?? false), 'said' => $said, 'answer' => $answer];
        };
    }

    public function testWhoeverIsNotRecognisedIsNotLetIn(): void
    {
        $client = $this->browser = self::createClient();

        self::call($client, null, 'tools/list');
        self::assertResponseStatusCodeSame(401);

        self::call($client, str_repeat('0', 64), 'tools/list');
        self::assertResponseStatusCodeSame(401);

        self::call($client, 'not a secret', 'tools/call', ['name' => 'new_game', 'arguments' => []]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testARequestLargerThanACallCanBeIsRefusedBeforeTheServerReadsIt(): void
    {
        $client = $this->browser = self::createClient();
        $secret = self::guest();

        // what a client says of itself is kept in a file by the SDK: megabytes of it must not get that far
        self::call($client, $secret, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => str_repeat('a', 20_000), 'version' => '1']]);
        self::assertResponseStatusCodeSame(413);

        // and whoever is not recognised is told so first, whatever they send
        self::call($client, null, 'initialize', ['clientInfo' => ['name' => str_repeat('a', 20_000)]]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testASecretIsWrittenInNoLineOfTheLog(): void
    {
        $this->browser = self::createClient();
        $secret = self::guest();

        $offered = self::call($this->browser, $secret, 'tools/list');
        self::assertResponseIsSuccessful();
        $names = array_column(\is_array($offered['tools'] ?? null) ? $offered['tools'] : [], 'name');
        sort($names);
        $expected = self::TOOLS;
        sort($expected);
        self::assertSame($expected, $names);
        self::call($this->browser, $secret, 'tools/call', ['name' => 'my_view', 'arguments' => ['table' => str_repeat('0', 32)]]);

        $log = self::getContainer()->get('logger');
        self::assertInstanceOf(ListedLog::class, $log);
        self::assertNotSame([], $log->lines);
        self::assertStringNotContainsString($secret, implode("\n", $log->lines));
    }

    /** @return string the secret of a new guest, as `bin/console kabbout:guest` prints it */
    private static function guest(): string
    {
        $from = NetworkAddress::of('2001:db8:'.bin2hex(random_bytes(2)).':'.bin2hex(random_bytes(2)).'::1');
        $enter = self::getContainer()->get(EnterAsGuest::class);
        self::assertInstanceOf(EnterAsGuest::class, $enter);
        $secret = $enter($from, null)->secret;
        self::assertNotNull($secret);

        return $secret->reveal();
    }

    /**
     * One call in the revision of the protocol that keeps no session: each request stands alone, and
     * says which revision it speaks.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> the result, or nothing when the server did not answer the call
     */
    private static function call(KernelBrowser $client, ?string $secret, string $method, array $params = []): array
    {
        $client->request('POST', '/mcp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD' => $method,
            ...(isset($params['name']) && \is_string($params['name']) ? ['HTTP_MCP_NAME' => $params['name']] : []),
            ...(null === $secret ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$secret]),
            'REMOTE_ADDR' => '2001:db8:'.bin2hex(random_bytes(2)).'::1',
        ], content: json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [
            ...$params,
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientInfo' => ['name' => 'kabbout-tests', 'version' => '1.0.0'],
                'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
            ],
        ]], \JSON_THROW_ON_ERROR));
        $body = $client->getInternalResponse()->getContent();
        if (1 === preg_match('/^data: (.*)$/m', $body, $event)) {
            $body = $event[1];
        }
        $answer = json_decode($body, true);
        /** @var array<string, mixed> $result */
        $result = \is_array($answer) && \is_array($answer['result'] ?? null) ? $answer['result'] : [];

        return $result;
    }
}
