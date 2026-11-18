<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Domain\Player;
use Symfony\Bundle\SecurityBundle\Security;

/** Who is asking, as Symfony Security recognised the request. */
final readonly class SecurityCurrentPlayer implements CurrentPlayer
{
    public function __construct(private Security $security)
    {
    }

    public function player(): ?Player
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityPlayer ? $user->player : null;
    }
}
