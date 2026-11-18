<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\Secret;

/**
 * Who is in, and the secret to hand them when they have just come in: that is the only time the server
 * holds it. Whoever was in already has theirs, and there is none here.
 */
final readonly class Entered
{
    public function __construct(public Player $player, #[\SensitiveParameter] public ?Secret $secret)
    {
    }
}
