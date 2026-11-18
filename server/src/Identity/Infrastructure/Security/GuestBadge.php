<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Kabbout\Identity\Application\RecogniseGuest;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * From what a request presents as a guest's secret to the user Symfony Security carries: the one step
 * every way in of a guest shares, whatever the secret came in (a cookie from the page, a header from
 * an MCP client).
 *
 * Security knows the player by their id, which it may write in its log. It never sees the secret.
 */
final readonly class GuestBadge
{
    public function __construct(private RecogniseGuest $recognise, private LoggerInterface $logger)
    {
    }

    /**
     * @throws BadCredentialsException        when it is nobody's secret
     * @throws AuthenticationServiceException when the players cannot be read: logged as an error
     */
    public function of(#[\SensitiveParameter] string $presented): UserBadge
    {
        try {
            $player = ($this->recognise)($presented);
        } catch (PlayersUnavailable $unavailable) {
            $this->logger->error('The players cannot be read: whoever asks is taken for nobody.', ['exception' => $unavailable]);

            throw new AuthenticationServiceException('The players cannot be read.', previous: $unavailable);
        }
        if (null === $player) {
            throw new BadCredentialsException('This is no player’s secret.');
        }

        return new UserBadge($player->id->toString(), static fn (): SecurityPlayer => new SecurityPlayer($player));
    }
}
