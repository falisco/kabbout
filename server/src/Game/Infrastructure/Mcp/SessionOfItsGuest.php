<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Mcp;

use Kabbout\Identity\Application\CurrentPlayer;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An MCP session over HTTP is its guest's. A client of the older revisions of the protocol opens one,
 * and the SDK knows it by its id alone: any guest who learnt the id could use the session, or close it.
 * Nothing of ours is in a session, so there was nothing to read; this makes it nothing to break either.
 *
 * When the server hands out a session id, who it was handed to is kept, as long as the SDK keeps the
 * session. A request that names the session of somebody else is answered as the SDK answers a session
 * it does not know.
 */
final readonly class SessionOfItsGuest
{
    private const string ROUTES = '_mcp_endpoint_';
    private const string HEADER = 'Mcp-Session-Id';
    /** the bundle's own life of a session, in seconds (mcp.servers.kabbout.session.ttl, left at its default) */
    private const int SECONDS_KEPT = 3600;

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
        if (\is_string($owner) && $owner !== $this->current->player()?->id->toString()) {
            throw new NotFoundHttpException('Session not found or has expired.');
        }
        if (\is_string($owner)) {
            // used again: kept as long again, like the session itself
            $this->cache->save($item->expiresAfter(self::SECONDS_KEPT));
        }
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
