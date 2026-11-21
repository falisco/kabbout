<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/** What the player's seat sees at their table. Nothing is played. */
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
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named): SeatView
    {
        $table = ($this->own)($player, $from, $named);

        return $this->engine->view($table->state, $table->seat);
    }
}
