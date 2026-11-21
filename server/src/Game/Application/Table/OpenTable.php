<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/**
 * A player sits down: a new game is dealt, robots of the chosen level take the three other seats and
 * play until it is the player's turn. The table is theirs alone. The player may bring the conventions
 * card they and their partner bid by: the robot facing them bids by it for the whole game, and the two
 * others keep the classic card.
 *
 * A table may be opened with a seed of the player's choosing, so that a test or a bench plays the same
 * cards twice. Whoever chose the seed could work out the four hands: such a table proves nothing about
 * how well its player plays, and it is for them to know. Only its first deal is the same every time:
 * the cards of the next ones depend on how many bids the one before took.
 */
final readonly class OpenTable
{
    public function __construct(private Engine $engine, private RobotsPlay $robots, private TableAllowance $allowance, private Now $now)
    {
    }

    /**
     * @param Seed|null           $seed what the game draws from; left out, a secret nobody knows
     * @param ConventionCard|null $card the conventions the player and the robot facing them bid by; left out, the classic ones
     *
     * @throws PlayedTooFast
     * @throws EngineRefused
     * @throws EngineUnavailable
     * @throws TablesUnavailable
     */
    public function __invoke(PlayerId $player, NetworkAddress $from, Level $level, Seat $seat, #[\SensitiveParameter] ?Seed $seed = null, ?ConventionCard $card = null): Turn
    {
        $this->allowance->pace($from);
        $this->allowance->open($from);
        $secret = $seed ?? Seed::random();
        $dealt = $this->engine->newGame($secret->derive('deal'));
        $table = new Table(TableId::random(), Owner::of($player->toString()), $seat, $level, $secret, 0, $dealt->state, null, $this->now->seconds(), $card);

        return ($this->robots)($table, $dealt, 0);
    }
}
