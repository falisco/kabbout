<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Domain\Proof;

/** Players for tests: in a list, by the proof they are known by, for the test to read. They can be made unavailable. */
final class ListedPlayers implements Players
{
    /** @var array<string, Player> */
    public array $known = [];

    /** whether what holds the players can be reached */
    public bool $unavailable = false;

    public function admit(Player $player, Proof $proof): bool
    {
        $this->unavailable && throw new PlayersUnavailable();
        foreach ($this->known as $other) {
            if ($other->name->toString() === $player->name->toString()) {
                return false;
            }
        }
        $this->known[$proof->toString()] = $player;

        return true;
    }

    public function seenAgain(Proof $proof): ?Player
    {
        $this->unavailable && throw new PlayersUnavailable();

        return $this->known[$proof->toString()] ?? null;
    }
}
