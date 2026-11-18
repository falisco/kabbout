<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Kabbout\Identity\Application\RecogniseGuest;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The way in of a guest: the secret in their cookie, recognised by RecogniseGuest. Signing in will be
 * a second authenticator on the same firewall.
 *
 * A request with no cookie is not looked at. A cookie that is nobody's secret is no error either: the
 * request goes on as nobody's (both answers below are null), so a guest the server has forgotten can
 * come in again. The same goes when the players cannot be read: the routes that need nobody still
 * answer, and the failure is logged as an error.
 *
 * Security knows the player by their id, which it may write in its log. It never sees the secret.
 */
final class GuestAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly RecogniseGuest $recognise, private readonly LoggerInterface $logger)
    {
    }

    public function supports(Request $request): bool
    {
        return null !== GuestCookie::presentedIn($request);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $player = ($this->recognise)(GuestCookie::presentedIn($request) ?? '');
        } catch (PlayersUnavailable $unavailable) {
            $this->logger->error('The players cannot be read: whoever asks is taken for nobody.', ['exception' => $unavailable]);

            throw new AuthenticationServiceException('The players cannot be read.', previous: $unavailable);
        }
        if (null === $player) {
            throw new BadCredentialsException('The cookie is no player’s secret.');
        }

        return new SelfValidatingPassport(new UserBadge($player->id->toString(), static fn (): SecurityPlayer => new SecurityPlayer($player)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
