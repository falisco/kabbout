<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * A whole game as the engine keeps it, all four hands and the robots' reasons included.
 * It never leaves the server: a player is only ever sent a SeatView.
 * Its content belongs to the engine; the server reads the little it needs and hands the rest back untouched.
 */
final readonly class GameState
{
    private Phase $phase;
    private Seat $turn;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $phase = $data['phase'] ?? null;
        $turn = $data['turn'] ?? null;
        $this->phase = (\is_string($phase) ? Phase::tryFrom($phase) : null) ?? throw new \InvalidArgumentException('Not a game state: no phase.');
        $this->turn = (\is_int($turn) ? Seat::tryFrom($turn) : null) ?? throw new \InvalidArgumentException('Not a game state: no seat to act.');
    }

    public function phase(): Phase
    {
        return $this->phase;
    }

    /** The seat to bid or to play; it means nothing once the deal is over. */
    public function turn(): Seat
    {
        return $this->turn;
    }

    /**
     * For the engine only: the four hands and the robots' reasons are in it. There is no other way out
     * of this class, so that a state cannot be sent to a player by mistake.
     *
     * @return array<string, mixed>
     */
    public function toEngineArray(): array
    {
        return $this->data;
    }
}
