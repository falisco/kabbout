<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

use Kabbout\Identity\Domain\Chance;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;

/**
 * Somebody comes in with nothing to type: a new guest, a name of their own, and the secret that will
 * prove it is them next time. The name is drawn at random, and drawn again while another player holds it.
 */
final readonly class EnterAsGuest
{
    /** with ten million names, a second draw is rare; this many in a row means chance is broken or the names are spent */
    private const int DRAWS = 20;

    public function __construct(private Players $players, private Chance $chance)
    {
    }

    /** @throws NoNameFree */
    public function __invoke(): Entered
    {
        $id = PlayerId::random();
        $secret = Secret::random();
        for ($draw = 0; $draw < self::DRAWS; ++$draw) {
            $player = Player::guest($id, PlayerName::guest($this->chance));
            if ($this->players->admit($player, Proof::of($secret))) {
                return new Entered($player, $secret);
            }
        }

        throw new NoNameFree();
    }
}
