<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;

/** Where the player stands at their table: what their seat sees and what they may do. Nothing is played. */
final readonly class SeeTable
{
    public function __construct(private OwnTable $own, private Engine $engine)
    {
    }

    /**
     * @throws PlayedTooFast
     * @throws NoSuchTable
     * @throws TableExpired
     * @throws EngineUnavailable
     * @throws TablesUnavailable
     */
    public function __invoke(Player $player, NetworkAddress $from, string $named): Turn
    {
        $table = ($this->own)($player, $from, $named);

        return new Turn($table->id, [], $this->engine->view($table->state, $table->seat), $this->engine->legalMoves($table->state, $table->seat));
    }
}
