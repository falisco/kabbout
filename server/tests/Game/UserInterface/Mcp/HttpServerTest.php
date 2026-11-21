<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Tests\Support\ListedLog;
use Mcp\Schema\JsonRpc\Error;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

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

    public function testASessionOfTheOlderRevisionIsItsGuestsAndNobodyElseUsesOrClosesIt(): void
    {
        $client = $this->browser = self::createClient();
        $owner = self::guest();
        $other = self::guest();

        // the handshake, as a client of revision 2025-11-25 makes it: the server hands out a session
        self::session($client, $owner, null, 'POST', ['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'kabbout-tests', 'version' => '1']]]);
        self::assertResponseIsSuccessful();
        $session = $client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotNull($session);
        self::session($client, $owner, $session, 'POST', ['method' => 'notifications/initialized']);
        self::assertResponseIsSuccessful();

        // with it, the tools: the guest is who the secret says, session or not
        $opened = self::session($client, $owner, $session, 'POST', ['id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'new_game', 'arguments' => new \stdClass()]]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"handSizes":[8,8,8,8]', $opened);

        // what anybody is told of a session that does not exist
        $unknown = self::session($client, $other, '11111111-2222-4333-8444-555555555555', 'POST', ['id' => 3, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(404);
        // the SDK's own words for it (Mcp\Server\Protocol), as its transport writes them
        self::assertSame(json_encode(Error::forInvalidRequest('Session not found or has expired.'), \JSON_THROW_ON_ERROR), $unknown);

        // another guest who learnt the id is told the same, word for word, however the id is spelt
        foreach ([$session, strtoupper($session), Uuid::fromString($session)->toBase58(), Uuid::fromString($session)->toBase32()] as $spelt) {
            self::assertSame($unknown, self::session($client, $other, $spelt, 'POST', ['id' => 3, 'method' => 'tools/list']), $spelt);
            self::assertResponseStatusCodeSame(404);
            self::session($client, $other, $spelt, 'DELETE', null);
            self::assertResponseStatusCodeSame(404);
        }

        // and a session whose owner was forgotten is nobody's to take: not the other guest's, not again its own
        $kept = self::getContainer()->get('cache.tables');
        self::assertInstanceOf(CacheItemPoolInterface::class, $kept);
        $kept->clear();
        self::assertSame($unknown, self::session($client, $other, $session, 'POST', ['id' => 3, 'method' => 'tools/list']));
        self::assertSame($unknown, self::session($client, $owner, $session, 'POST', ['id' => 3, 'method' => 'tools/list']));
    }

    public function testASessionStaysItsGuestsWhoClosesIt(): void
    {
        $client = $this->browser = self::createClient();
        $owner = self::guest();
        $other = self::guest();
        self::session($client, $owner, null, 'POST', ['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'kabbout-tests', 'version' => '1']]]);
        $session = $client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotNull($session);
        self::session($client, $owner, $session, 'POST', ['method' => 'notifications/initialized']);
        self::session($client, $other, strtoupper($session), 'DELETE', null);
        self::assertResponseStatusCodeSame(404);

        // it is still its guest's, who closes it
        self::session($client, $owner, $session, 'POST', ['id' => 4, 'method' => 'tools/list']);
        self::assertResponseIsSuccessful();
        self::session($client, $owner, $session, 'DELETE', null);
        self::assertResponseIsSuccessful();
    }

    public function testTheSchemeOfTheHeaderIsReadInAnyCase(): void
    {
        $client = $this->browser = self::createClient();
        $secret = self::guest();

        foreach (['bearer', 'BEARER', 'Bearer'] as $scheme) {
            $client->request('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => $scheme.' '.$secret], content: '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"t","version":"1"}}}');
            self::assertResponseIsSuccessful($scheme);
        }
        $client->request('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Basic '.$secret], content: '{}');
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

    /**
     * One request of a client of the older revision, which names its session in a header.
     *
     * @param array<string, mixed>|null $message
     */
    private static function session(KernelBrowser $client, string $secret, ?string $session, string $method, ?array $message): string
    {
        $client->request($method, '/mcp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer '.$secret,
            ...(null === $session ? [] : ['HTTP_MCP_SESSION_ID' => $session, 'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25']),
            'REMOTE_ADDR' => '2001:db8:'.bin2hex(random_bytes(2)).'::1',
        ], content: null === $message ? null : json_encode(['jsonrpc' => '2.0', ...$message], \JSON_THROW_ON_ERROR));

        return $client->getInternalResponse()->getContent();
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
