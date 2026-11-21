<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Table\TableId;

/**
 * The count at a table, as everybody at it may read it: both teams' scores, the contract, the sheet of
 * the deal just played, the deals before it, and who won once somebody has; with the team of the seat
 * it is read from.
 */
final readonly class Score
{
    /** what a view holds of the count; the cards and the auction are left to the view */
    private const array FIELDS = ['phase', 'round', 'target', 'scoring', 'scores', 'contract', 'result', 'history', 'winner'];

    /** @param array<string, mixed> $count */
    private function __construct(private TableId $table, private int $team, private array $count)
    {
    }

    public static function of(TableId $table, SeatView $view): self
    {
        return new self($table, $view->seat()->team(), array_intersect_key($view->toArray(), array_flip(self::FIELDS)));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['table' => $this->table->toString(), 'team' => $this->team, ...$this->count];
    }
}
