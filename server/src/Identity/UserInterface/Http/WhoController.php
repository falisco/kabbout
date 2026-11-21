<?php

declare(strict_types=1);

namespace Kabbout\Identity\UserInterface\Http;

use Kabbout\Identity\Application\CurrentPlayer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/session: who is asking. `player` holds their name, or is null when it is nobody the server
 * knows: no cookie, a cookie that is nobody's secret and a guest since forgotten are answered alike.
 */
final readonly class WhoController
{
    public function __construct(private CurrentPlayer $current)
    {
    }

    #[Route('/api/session', name: 'session_who', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return SessionAnswer::of($this->current->player());
    }
}
