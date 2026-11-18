<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\Secret;

/** Who has just come in, and the secret to hand them: this is the only time the server holds it. */
final readonly class Entered
{
    public function __construct(public Player $player, #[\SensitiveParameter] public Secret $secret)
    {
    }
}
