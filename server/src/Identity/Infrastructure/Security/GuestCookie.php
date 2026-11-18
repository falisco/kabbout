<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Kabbout\Identity\Domain\Secret;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * The cookie a guest's secret travels in. The page cannot read it (HttpOnly), no other site can make
 * the browser send it (SameSite=Strict), and it only goes to the API. It is marked Secure when the
 * request that sets it came over HTTPS: the game also runs on plain http://localhost.
 *
 * The secret is sent once, when the guest enters, so the cookie outlives the guest: the browser may
 * keep it DAYS_IN_BROWSER days, about the longest browsers allow, and it is the server that forgets a
 * guest (Players::DAYS_KEPT). A guest who plays every day is still a new guest after that many days.
 */
#[Exclude]
final readonly class GuestCookie
{
    public const string NAME = 'kabbout_guest';
    private const string PATH = '/api';
    private const int DAYS_IN_BROWSER = 400;

    public static function holding(#[\SensitiveParameter] Secret $secret): Cookie
    {
        return Cookie::create(self::NAME, $secret->reveal(), \sprintf('+%d days', self::DAYS_IN_BROWSER), self::PATH, httpOnly: true, sameSite: Cookie::SAMESITE_STRICT);
    }

    /** What a request presents as a guest's secret, if anything; a cookie that is a list of things is nothing. */
    public static function presentedIn(Request $request): ?string
    {
        $presented = $request->cookies->all()[self::NAME] ?? null;

        return \is_string($presented) ? $presented : null;
    }
}
