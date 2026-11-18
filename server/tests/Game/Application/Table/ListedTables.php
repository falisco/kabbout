<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Application\Table\Table;
use Kabbout\Game\Application\Table\TableId;
use Kabbout\Game\Application\Table\Tables;

/** Tables for tests: kept in a list, with a count of how many times one was written. */
final class ListedTables implements Tables
{
    /** @var array<string, Table> */
    public array $kept = [];
    public int $saved = 0;

    public function find(TableId $id): ?Table
    {
        return $this->kept[$id->toString()] ?? null;
    }

    public function save(Table $table): void
    {
        ++$this->saved;
        $this->kept[$table->id->toString()] = $table;
    }
}
