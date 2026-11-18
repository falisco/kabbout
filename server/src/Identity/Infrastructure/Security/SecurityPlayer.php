<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Kabbout\Identity\Domain\Player;
use Symfony\Component\Security\Core\User\UserInterface;

/** A player as Symfony Security wants its users: the domain's player, held, so that the domain knows nothing of Symfony. */
final readonly class SecurityPlayer implements UserInterface
{
    public function __construct(public Player $player)
    {
    }

    public function getRoles(): array
    {
        return ['ROLE_PLAYER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->player->id->toString();
    }
}
