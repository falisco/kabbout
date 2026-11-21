<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayersUnavailable;

/**
 * Who is asking, as a door to the server knows it. A port: over HTTP, Symfony Security answers, from
 * whatever way in recognised the request. A door asks here and reads no cookie or header itself, so a
 * second way to be recognised (signing in, one day) changes no door.
 */
interface CurrentPlayer
{
    /**
     * The player asking, or null when it is nobody the server knows.
     *
     * Where asking is what makes somebody a player (a process started for one client has no other way
     * to come in), this is where they come in, and where coming in can be refused.
     *
     * @throws EnteredTooOften
     * @throws NoNameFree
     * @throws PlayersUnavailable
     */
    public function player(): ?Player;
}
