<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/** The count at the player's table, as the engine made it. */
final readonly class ReadScore
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
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named): Score
    {
        $table = ($this->own)($player, $from, $named);

        return Score::of($table->id, $this->engine->view($table->state, $table->seat));
    }
}
