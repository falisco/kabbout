<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Domain\Table\Table;
use Kabbout\Game\Domain\Table\TableId;
use Kabbout\Game\Domain\Table\Tables;

/**
 * Tables for tests: kept in a list. It writes down each time a table was read or written, and whether
 * that table was held then, so that a test can say what happened under the lock and what did not.
 */
final class ListedTables implements Tables
{
    /** @var array<string, Table> */
    public array $kept = [];
    public int $saved = 0;
    /** @var list<string> "find held", "save free", ... in order */
    public array $journal = [];
    /** @var array<string, true> the tables held now */
    private array $held = [];

    public function find(TableId $id): ?Table
    {
        $this->journal[] = 'find '.(isset($this->held[$id->toString()]) ? 'held' : 'free');

        return $this->kept[$id->toString()] ?? null;
    }

    public function save(Table $table): void
    {
        $this->journal[] = 'save '.(isset($this->held[$table->id->toString()]) ? 'held' : 'free');
        ++$this->saved;
        $this->kept[$table->id->toString()] = $table;
    }

    public function alone(TableId $id, \Closure $play): mixed
    {
        if (isset($this->held[$id->toString()])) {
            throw new \LogicException('A table is held twice: with a real lock this would wait for ever.');
        }
        $this->held[$id->toString()] = true;
        try {
            return $play();
        } finally {
            unset($this->held[$id->toString()]);
        }
    }
}
