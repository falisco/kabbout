<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Domain\Secret;
use Kabbout\Identity\Infrastructure\Security\GuestCookie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class GuestCookieTest extends TestCase
{
    public function testTheCookieIsOutOfReachOfThePageAndOfOtherSitesAndOnlyGoesToTheApi(): void
    {
        $secret = Secret::random();

        $cookie = GuestCookie::holding($secret);

        self::assertSame([GuestCookie::NAME, $secret->reveal()], [$cookie->getName(), $cookie->getValue()]);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertSame('/api', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        // the browser keeps it long after the server would have forgotten a guest it no longer sees
        self::assertEqualsWithDelta(time() + 400 * 24 * 3600, $cookie->getExpiresTime(), 5);
    }

    public function testTheCookieIsMarkedSecureWhenTheRequestCameOverHttpsAndOnlyThen(): void
    {
        $cookie = GuestCookie::holding(Secret::random());
        self::assertFalse($cookie->isSecure());

        $cookie->setSecureDefault(true);

        self::assertTrue($cookie->isSecure());
    }

    public function testACookieThatIsAListOfThingsPresentsNothing(): void
    {
        self::assertSame('abc', GuestCookie::presentedIn(new Request(cookies: [GuestCookie::NAME => 'abc'])));
        self::assertNull(GuestCookie::presentedIn(new Request(cookies: [GuestCookie::NAME => ['abc']])));
        self::assertNull(GuestCookie::presentedIn(new Request(cookies: ['another' => 'abc'])));
    }
}
