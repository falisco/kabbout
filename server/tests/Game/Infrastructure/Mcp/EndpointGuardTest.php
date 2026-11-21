<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Mcp;

use Kabbout\Game\Infrastructure\Mcp\EndpointGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** What the MCP endpoint takes over HTTP, whatever the request asks: so many a minute from one address, none larger than a call. */
final class EndpointGuardTest extends TestCase
{
    public function testSoManyRequestsAMinuteFromOneAddressWhateverTheyAsk(): void
    {
        $guard = self::guard(2);
        $guard(self::request('203.0.113.1', '{"method":"initialize"}'));
        $guard(self::request('203.0.113.1', '{"method":"tools/list"}'));
        // another address has its own count
        $guard(self::request('203.0.113.2', '{"method":"initialize"}'));

        try {
            $guard(self::request('203.0.113.1', '{"method":"initialize"}'));
            self::fail('A third request went through.');
        } catch (TooManyRequestsHttpException $tooMany) {
            self::assertSame(429, $tooMany->getStatusCode());
            self::assertGreaterThanOrEqual(1, $tooMany->getHeaders()['Retry-After'] ?? 0);
        }
    }

    public function testARequestLargerThanACallCanBeIsRefusedAndNotCounted(): void
    {
        $guard = self::guard(1);
        $guard(self::request('203.0.113.1', str_repeat('a', EndpointGuard::MOST_BYTES)));

        try {
            self::guard(1)(self::request('203.0.113.1', str_repeat('a', EndpointGuard::MOST_BYTES + 1)));
            self::fail('A request of any size went through.');
        } catch (HttpException $tooLarge) {
            self::assertSame(413, $tooLarge->getStatusCode());
        }
    }

    public function testTheRestOfTheServerIsNotItsBusiness(): void
    {
        $guard = self::guard(1);

        // another route, and a request inside a request: neither looked at, neither counted
        $guard(self::request('203.0.113.1', str_repeat('a', EndpointGuard::MOST_BYTES + 1), 'coach'));
        $guard(self::request('203.0.113.1', '{}', '_mcp_endpoint_kabbout', HttpKernelInterface::SUB_REQUEST));
        $guard(self::request('203.0.113.1', '{}'));

        $this->expectException(TooManyRequestsHttpException::class);
        $guard(self::request('203.0.113.1', '{}'));
    }

    private static function guard(int $aMinute): EndpointGuard
    {
        return new EndpointGuard(new RateLimiterFactory(['id' => 'mcp', 'policy' => 'sliding_window', 'limit' => $aMinute, 'interval' => '1 minute'], new InMemoryStorage()));
    }

    private static function request(string $from, string $body, string $route = '_mcp_endpoint_kabbout', int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/mcp', 'POST', server: ['REMOTE_ADDR' => $from], content: $body);
        $request->attributes->set('_route', $route);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }
}
