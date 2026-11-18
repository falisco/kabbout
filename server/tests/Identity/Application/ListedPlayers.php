<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\Proof;

/** Players for tests: in a list, by the proof they are known by, for the test to read. */
final class ListedPlayers implements Players
{
    /** @var array<string, Player> */
    public array $known = [];

    public function admit(Player $player, Proof $proof): bool
    {
        foreach ($this->known as $other) {
            if ($other->name->toString() === $player->name->toString()) {
                return false;
            }
        }
        $this->known[$proof->toString()] = $player;

        return true;
    }

    public function provenBy(Proof $proof): ?Player
    {
        return $this->known[$proof->toString()] ?? null;
    }
}
