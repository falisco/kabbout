<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The way in of a client that is no browser (an MCP client over HTTP): the guest's secret in the
 * `Authorization: Bearer` header, read by Symfony's own access-token authenticator and recognised here.
 * The same guest as the one a cookie names: a secret is a secret, whatever carries it.
 */
final readonly class GuestTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private GuestBadge $badge)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        return $this->badge->of($accessToken);
    }
}
