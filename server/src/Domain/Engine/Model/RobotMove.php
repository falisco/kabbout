<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Model;

use Kabbout\Domain\Engine\Enum\Seat;

/** What a robot would do for the seat to act. Nothing has been played yet: the action still has to be applied. */
final readonly class RobotMove
{
    public function __construct(public Seat $seat, public Action $action)
    {
    }

    /**
     * For the engine only: a robot's bid holds its reason, which names its cards.
     *
     * @return array{seat: int, action: array<string, mixed>}
     */
    public function toEngineArray(): array
    {
        return ['seat' => $this->seat->value, 'action' => $this->action->toEngineArray()];
    }

    /**
     * The move as the table may hear it: the seat and what it does, without the reason.
     *
     * @return array{seat: int, action: array<string, mixed>}
     */
    public function toPublicArray(): array
    {
        return ['seat' => $this->seat->value, 'action' => $this->action->withoutReason()->toEngineArray()];
    }
}
