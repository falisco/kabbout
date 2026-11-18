<?php

declare(strict_types=1);

namespace Kabbout\Identity\UserInterface\Http;

use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Infrastructure\Security\GuestCookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/session: come in as a guest. The answer is 201 with the new guest's name, and their secret
 * in a cookie, the only time it is sent. Whoever is already a player is answered 200 with their own
 * name and gets no new cookie. New guests are counted against the address of the request: past
 * KABBOUT_GUESTS_LIMIT a minute the answer is 429, with Retry-After.
 *
 * A browser says where a request comes from (Sec-Fetch-Site). One made by another site is refused:
 * its form could otherwise replace a visitor's guest by a new one. A client that is no browser sends
 * no such header and comes in.
 */
final readonly class EnterController
{
    /** what a browser says of a request the page itself made, or the visitor made by hand */
    private const array FROM_HERE = ['same-origin', 'none'];

    public function __construct(private EnterAsGuest $enter, private CurrentPlayer $current)
    {
    }

    #[Route('/api/session', name: 'session_enter', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!\in_array($request->headers->get('Sec-Fetch-Site') ?? 'none', self::FROM_HERE, true)) {
            return self::error(Response::HTTP_FORBIDDEN, 'cross_site', 'Only the game’s own page may enter for its visitor.');
        }
        try {
            $entered = ($this->enter)(NetworkAddress::of($request->getClientIp()), $this->current->player());
        } catch (EnteredTooOften $tooOften) {
            throw new TooManyRequestsHttpException($tooOften->retryAfter);
        } catch (NoNameFree) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'no_name_free', 'No name could be found for a new guest; try again.');
        } catch (PlayersUnavailable) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'players_unavailable', 'Nobody can come in right now; try again.');
        }
        if (null === $entered->secret) {
            return SessionAnswer::of($entered->player);
        }
        $answer = SessionAnswer::of($entered->player, Response::HTTP_CREATED);
        $answer->headers->setCookie(GuestCookie::holding($entered->secret));

        return $answer;
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status, ['Cache-Control' => 'no-store']);
    }
}
