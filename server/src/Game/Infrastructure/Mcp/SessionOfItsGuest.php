<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Mcp;

use Kabbout\Identity\Application\CurrentPlayer;
use Mcp\Schema\JsonRpc\Error;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * An MCP session over HTTP is its guest's. A client of the older revisions of the protocol opens one,
 * and the SDK knows it by its id alone: any guest who learnt the id could use the session, or close it.
 * Nothing of ours is in a session, so there was nothing to read; this makes it nothing to break either.
 *
 * When the server hands out a session id, who it was handed to is kept, longer than the SDK keeps the
 * session. A request that names a session is let through to the SDK only when the id is spelt as it was
 * handed out and was handed to this guest: the SDK reads other spellings of the same id (capitals,
 * base 58, base 32), and a session nobody is known to own is nobody's to take. Whatever else is answered
 * as the SDK answers a session it does not know, body included.
 */
final readonly class SessionOfItsGuest
{
    private const string ROUTES = '_mcp_endpoint_';
    private const string HEADER = 'Mcp-Session-Id';
    /** how the SDK spells the ids it hands out (Uuid::toRfc4122) */
    private const string HANDED_OUT = '/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D';
    /** what the SDK says of a session it does not know (Mcp\Server\Protocol) */
    private const string UNKNOWN = 'Session not found or has expired.';
    /**
     * Twice the bundle's own life of a session (mcp.servers.kabbout.session.ttl, left at its default of
     * an hour): an owner must not be forgotten before the session is.
     */
    private const int SECONDS_KEPT = 2 * 3600;

    public function __construct(private CurrentPlayer $current, #[Autowire(service: 'cache.tables')] private CacheItemPoolInterface $cache)
    {
    }

    #[AsEventListener]
    public function asked(RequestEvent $event): void
    {
        $session = $event->getRequest()->headers->get(self::HEADER);
        if (!$this->concerns($event->getRequest()->attributes->get('_route')) || null === $session) {
            return;
        }
        $item = $this->cache->getItem(self::key($session));
        $owner = $item->get();
        if (1 !== preg_match(self::HANDED_OUT, $session) || !\is_string($owner) || $owner !== $this->current->player()?->id->toString()) {
            $event->setResponse(new JsonResponse(Error::forInvalidRequest(self::UNKNOWN), 404));

            return;
        }
        // used again: kept as long again, like the session itself
        $this->cache->save($item->expiresAfter(self::SECONDS_KEPT));
    }

    #[AsEventListener]
    public function answered(ResponseEvent $event): void
    {
        $session = $event->getResponse()->headers->get(self::HEADER);
        $player = $this->current->player();
        if (!$this->concerns($event->getRequest()->attributes->get('_route')) || null === $session || null === $player) {
            return;
        }
        $item = $this->cache->getItem(self::key($session));
        if (!$item->isHit()) {
            $this->cache->save($item->set($player->id->toString())->expiresAfter(self::SECONDS_KEPT));
        }
    }

    private function concerns(mixed $route): bool
    {
        return \is_string($route) && str_starts_with($route, self::ROUTES);
    }

    private static function key(string $session): string
    {
        return 'mcp.session.'.hash('sha256', $session);
    }
}
