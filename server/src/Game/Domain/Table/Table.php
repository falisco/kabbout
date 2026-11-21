<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Table;

use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;

/**
 * A game the server holds for one player: they sit at one seat, robots play the three others. The
 * whole game is here, the four hands included, and it never leaves the server: what the player is
 * sent is their seat's view.
 *
 * One player and one seat today. A table with several players will hold a seat for each.
 */
final readonly class Table
{
    /** a table left alone this long is over */
    public const int SECONDS_OPEN = 3 * 3600;

    /**
     * @param Seed            $secret   what every draw of this game comes from: a new seed is derived for each call
     * @param int             $moves    the actions applied so far, robots' included: what the next derived seed is named after
     * @param DealRecord|null $lastDeal the last deal played to its end at this table: the only deal a review is about
     * @param int             $playedAt when something last happened here, in seconds
     */
    public function __construct(
        public TableId $id,
        public Owner $owner,
        public Seat $seat,
        public Level $level,
        #[\SensitiveParameter] public Seed $secret,
        public int $moves,
        public GameState $state,
        public ?DealRecord $lastDeal,
        public int $playedAt,
    ) {
    }

    public function isOf(Owner $somebody): bool
    {
        return $this->owner->is($somebody);
    }

    public function isOverAt(int $now): bool
    {
        return $now - $this->playedAt > self::SECONDS_OPEN;
    }

    /** The same table once something has been played. */
    public function after(GameState $state, int $moves, ?DealRecord $finished, int $now): self
    {
        return new self($this->id, $this->owner, $this->seat, $this->level, $this->secret, $moves, $state, $finished ?? $this->lastDeal, $now);
    }
}
