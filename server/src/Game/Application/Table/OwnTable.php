<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;

/**
 * The table a player names, if it is theirs and still open. Every capability of a table starts here,
 * so the pace, whose table it is and how long it lasts are said once.
 */
final readonly class OwnTable
{
    public function __construct(private Tables $tables, private TableAllowance $allowance, private Now $now)
    {
    }

    /**
     * @param string $named the name of the table as the player gave it
     *
     * @throws PlayedTooFast
     * @throws NoSuchTable       for a table that does not exist and for a table of somebody else, alike
     * @throws TableExpired
     * @throws TablesUnavailable
     */
    public function __invoke(Player $player, NetworkAddress $from, string $named): Table
    {
        $this->allowance->pace($from);
        $id = TableId::tryFrom($named);
        $table = null === $id ? null : $this->tables->find($id);
        if (null === $table || !$table->isOf($player->id)) {
            throw new NoSuchTable();
        }
        if ($table->isOverAt($this->now->seconds())) {
            throw new TableExpired();
        }

        return $table;
    }
}
