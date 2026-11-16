<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * What a robot would do for the seat to act. Nothing has been played yet: the action still has to be applied.
 * The action is the robot's own, reason included; what the table may hear of it is `$action->withoutReason()`.
 */
final readonly class RobotMove
{
    public function __construct(public Seat $seat, public Action $action)
    {
    }
}
