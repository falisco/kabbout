<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/** What the player may do now at their table, as the engine allows it. Nothing is played. */
final readonly class ListMoves
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
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named): LegalMoves
    {
        $table = ($this->own)($player, $from, $named);

        return $this->engine->legalMoves($table->state, $table->seat);
    }
}
