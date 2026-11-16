<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Model;

/** A game after one action, with what happened as the table may hear it (bids without their reasons). */
final readonly class Step
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(public GameState $state, public array $events)
    {
    }

    /**
     * For the engine only: the state is in it. The events alone may be sent to the players.
     *
     * @return array{state: array<string, mixed>, events: list<array<string, mixed>>}
     */
    public function toEngineArray(): array
    {
        return ['state' => $this->state->toEngineArray(), 'events' => $this->events];
    }
}
