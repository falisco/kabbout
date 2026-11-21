<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The way in of a guest from the page: the secret in their cookie, recognised through GuestBadge.
 * Signing in will be a second authenticator on the same firewall.
 *
 * A request with no cookie is not looked at. A cookie that is nobody's secret is no error either: the
 * request goes on as nobody's (both answers below are null), so a guest the server has forgotten can
 * come in again. The same goes when the players cannot be read: the routes that need nobody still
 * answer, and the failure is logged as an error.
 */
final class GuestAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly GuestBadge $badge)
    {
    }

    public function supports(Request $request): bool
    {
        return null !== GuestCookie::presentedIn($request);
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport($this->badge->of(GuestCookie::presentedIn($request) ?? ''));
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
