<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Player;

/**
 * Who is asking, as a door to the server knows it. A port: over HTTP, Symfony Security answers, from
 * whatever way in recognised the request. A door asks here and reads no cookie or header itself, so a
 * second way to be recognised (signing in, one day) changes no door.
 */
interface CurrentPlayer
{
    /** the player asking, or null when it is nobody the server knows */
    public function player(): ?Player;
}
