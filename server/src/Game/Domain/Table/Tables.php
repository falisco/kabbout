<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Table;

/**
 * Where the tables are kept between two calls. A port: a cache on disk today, a database the day
 * games must outlive a restart of everything.
 */
interface Tables
{
    /** @throws TablesUnavailable */
    public function find(TableId $id): ?Table;

    /** @throws TablesUnavailable when the table could not be written: nothing is played on a table that was not kept */
    public function save(Table $table): void;

    /**
     * Runs what plays at a table while nothing else can: a second action on the same table waits for
     * the first to be written, and then plays from the game the first one left. Without it two actions
     * sent at the same instant would each be answered from the same game, and their player would have
     * seen the robots answer a card that was never played.
     *
     * @template T
     *
     * @param \Closure(): T $play it reads the table, plays and saves
     *
     * @param-immediately-invoked-callable $play
     *
     * @return T
     *
     * @throws TablesUnavailable when the table cannot be held
     */
    public function alone(TableId $id, \Closure $play): mixed;
}
