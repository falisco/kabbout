<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/**
 * The last deal played to its end at the player's table. It is the deal the server holds, as the
 * server saw it played: a review is never asked about a record its asker wrote.
 */
final readonly class FinishedDeal
{
    public function __construct(private OwnTable $own)
    {
    }

    /**
     * @throws PlayedTooFast
     * @throws NoSuchTable
     * @throws TableExpired
     * @throws NoFinishedDeal
     * @throws TablesUnavailable
     */
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named): PlayedDeal
    {
        $table = ($this->own)($player, $from, $named);

        return new PlayedDeal($table->lastDeal ?? throw new NoFinishedDeal(), $table->seat, $table->card);
    }
}
