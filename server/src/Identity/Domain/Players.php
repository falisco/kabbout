<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * The players the server knows. A port: a cache pool holds them today, a database will when there are
 * accounts. Whatever holds them keeps two promises: a name belongs to one player at a time, and a
 * guest nobody has seen for DAYS_KEPT days is forgotten, name included.
 */
interface Players
{
    /** how long a guest is kept after the last time they were seen */
    public const int DAYS_KEPT = 30;

    /**
     * Takes a new player in, known from now on by this proof.
     *
     * @return bool false when another player holds the name: nothing was kept
     */
    public function admit(Player $player, Proof $proof): bool;

    /** The player this proof is the proof of, if any. Seeing a player is what keeps them known. */
    public function provenBy(Proof $proof): ?Player;
}
