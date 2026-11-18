<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

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
}
