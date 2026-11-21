<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Table\TableId;

/**
 * Where a player stands after something was played at their table: what the table heard since their
 * action (the robots' bids and cards, without their reasons), what their seat sees, and what they may do.
 * Nothing in it is another seat's.
 */
final readonly class Turn
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(public TableId $table, public array $events, public SeatView $view, public LegalMoves $legal)
    {
    }

    /** @return array{table: string, events: list<array<string, mixed>>, view: array<string, mixed>, legal: array<string, mixed>} */
    public function toArray(): array
    {
        return ['table' => $this->table->toString(), 'events' => $this->events, 'view' => $this->view->toArray(), 'legal' => $this->legal->toArray()];
    }
}
