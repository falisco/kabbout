<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Mcp;

use Kabbout\Identity\Domain\NetworkAddress;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * What the MCP endpoint takes over HTTP before the bundle's controller sees a request: so many
 * requests a minute from one address, and none larger than a call to a tool can be.
 *
 * It is the transport's guard, not a rule of the game: the calls to the tables have their own count,
 * in their capabilities, which holds whatever the door. This one is for what reaches no capability.
 * A client of the older revisions of the protocol opens a session first, and the SDK keeps a file for
 * it holding whatever the client said of itself: unguarded, anybody let in could write megabytes a
 * request, as fast as they could send them.
 *
 * It runs after Symfony Security: whoever is not recognised was answered 401 and is not counted.
 */
#[AsEventListener]
final readonly class EndpointGuard
{
    /** the bundle names its routes `_mcp_endpoint_<server>` (Symfony\AI\McpBundle\Routing\RouteLoader) */
    private const string ROUTES = '_mcp_endpoint_';
    /** a call to a tool is a few hundred bytes; the SDK itself would take four megabytes */
    public const int MOST_BYTES = 16 * 1024;

    public function __construct(private RateLimiterFactoryInterface $mcpLimiter)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!$event->isMainRequest() || !\is_string($route) || !str_starts_with($route, self::ROUTES)) {
            return;
        }
        if (\strlen($request->getContent()) > self::MOST_BYTES) {
            throw new HttpException(413, 'A request to the MCP server is a few hundred bytes.');
        }
        $limit = $this->mcpLimiter->create(NetworkAddress::of($request->getClientIp())->key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
