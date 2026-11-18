<?php

declare(strict_types=1);

namespace Kabbout\Identity\UserInterface\Http;

use Kabbout\Api\Domain\Envelope;
use Kabbout\Api\Domain\FromThePage;
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
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * POST /api/session: come in as a guest. The answer is 201 with the new guest's name, and their secret
 * in a cookie, the only time it is sent. Whoever is already a player is answered 200 with their own
 * name and gets no new cookie. New guests are counted against the address of the request: past
 * KABBOUT_GUESTS_LIMIT a minute the answer is 429, with Retry-After.
 *
 * Only the game's own page may come in for its visitor (FromThePage): the form of another site could
 * otherwise replace a visitor's guest by a new one. Symfony makes the check before this is called, and
 * answers 401 by itself.
 */
final readonly class EnterController
{
    public function __construct(private EnterAsGuest $enter, private CurrentPlayer $current)
    {
    }

    #[Route('/api/session', name: 'session_enter', methods: ['POST'])]
    #[IsCsrfTokenValid(FromThePage::CHECK, tokenKey: FromThePage::HEADER, tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function __invoke(Request $request): JsonResponse
    {
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
        return new JsonResponse(Envelope::error($code, $message), $status);
    }
}
