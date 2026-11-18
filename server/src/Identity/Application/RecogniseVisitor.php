<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;

/**
 * A secret in, a player out. What is no secret at all, a secret nobody was given and the secret of a
 * guest since forgotten get the same answer, nobody: whoever tries secrets learns nothing from the difference.
 */
final readonly class RecogniseVisitor
{
    public function __construct(private Players $players)
    {
    }

    public function __invoke(#[\SensitiveParameter] string $presented): ?Player
    {
        $secret = Secret::tryFrom($presented);

        return null === $secret ? null : $this->players->provenBy(Proof::of($secret));
    }
}
