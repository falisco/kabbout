<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;

/**
 * A player sits down: a new game is dealt, robots of the chosen level take the three other seats and
 * play until it is the player's turn. The table is theirs alone.
 *
 * A table may be opened with a seed of the player's choosing, so that a test or a bench plays the same
 * cards twice. Whoever chose the seed could work out the four hands: such a table proves nothing about
 * how well its player plays, and it is for them to know.
 */
final readonly class OpenTable
{
    public function __construct(private Engine $engine, private RobotsPlay $robots, private TableAllowance $allowance, private Now $now)
    {
    }

    /**
     * @param Seed|null $seed what the game draws from; left out, a secret nobody knows
     *
     * @throws PlayedTooFast
     * @throws EngineRefused
     * @throws EngineUnavailable
     * @throws TablesUnavailable
     */
    public function __invoke(Player $player, NetworkAddress $from, Level $level, Seat $seat, #[\SensitiveParameter] ?Seed $seed = null): Turn
    {
        $this->allowance->pace($from);
        $secret = $seed ?? Seed::random();
        $dealt = $this->engine->newGame($secret->derive('deal'));
        $table = new Table(TableId::random(), $player->id, $seat, $level, $secret, 0, $dealt->state, null, $this->now->seconds());

        return ($this->robots)($table, $dealt, 0);
    }
}
