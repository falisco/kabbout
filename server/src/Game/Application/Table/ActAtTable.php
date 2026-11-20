<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/**
 * The player bids, plays a card or asks for the next deal; then the robots play until it is the
 * player's turn again. Whether the action is allowed is the engine's to say: when it says no, the
 * table is left as it was and the player is told what they may do.
 *
 * One action at a time at a table: the game is read, played and written while nothing else can play
 * at it. A player who sends two cards at once sees the robots answer one, and the other refused or
 * played after it, never both from the same game.
 */
final readonly class ActAtTable
{
    public function __construct(private OwnTable $own, private Engine $engine, private RobotsPlay $robots)
    {
    }

    /**
     * @throws PlayedTooFast
     * @throws NoSuchTable
     * @throws TableExpired
     * @throws MoveRefused
     * @throws EngineUnavailable
     * @throws TablesUnavailable
     */
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named, Action $action): Turn
    {
        return $this->own->alone($player, $from, $named, function (Table $table) use ($action): Turn {
            try {
                // a player's action carries no reason: only a robot's does
                $step = $this->engine->apply($table->state, $table->seat, $action->withoutReason(), $table->secret->derive('move '.$table->moves));
            } catch (EngineRefused $refused) {
                throw new MoveRefused($refused->getMessage(), $this->engine->legalMoves($table->state, $table->seat));
            }

            return ($this->robots)($table, $step, $table->moves + 1);
        });
    }
}
