<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Table\Owner;
use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use Kabbout\Game\Domain\Table\Tables;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

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
     * To look at the table: nothing is held, and nothing may be written from what is read here.
     *
     * @param string $named the name of the table as the player gave it
     *
     * @throws PlayedTooFast
     * @throws NoSuchTable       for a table that does not exist and for a table of somebody else, alike
     * @throws TableExpired
     * @throws TablesUnavailable
     */
    public function __invoke(PlayerId $player, NetworkAddress $from, string $named): Table
    {
        $this->allowance->pace($from);

        return $this->theirs($player, TableId::tryFrom($named) ?? throw new NoSuchTable());
    }

    /**
     * To play at the table: it is read once nothing else can play at it, and held until what plays
     * has written it. Two actions sent at the same instant are played one after the other.
     *
     * @template T
     *
     * @param \Closure(Table): T $play
     *
     * @param-immediately-invoked-callable $play
     *
     * @return T
     *
     * @throws PlayedTooFast
     * @throws NoSuchTable
     * @throws TableExpired
     * @throws TablesUnavailable
     */
    public function alone(PlayerId $player, NetworkAddress $from, string $named, \Closure $play): mixed
    {
        $this->allowance->pace($from);
        $id = TableId::tryFrom($named) ?? throw new NoSuchTable();

        return $this->tables->alone($id, fn (): mixed => $play($this->theirs($player, $id)));
    }

    private function theirs(PlayerId $player, TableId $id): Table
    {
        $table = $this->tables->find($id);
        if (null === $table || !$table->isOf(Owner::of($player->toString()))) {
            throw new NoSuchTable();
        }
        if ($table->isOverAt($this->now->seconds())) {
            throw new TableExpired();
        }

        return $table;
    }
}
