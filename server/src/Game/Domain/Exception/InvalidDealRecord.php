<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Exception;

/** What was sent as a recorded deal does not have the shape of one. The sender's mistake, not ours. */
final class InvalidDealRecord extends \RuntimeException implements EngineException
{
}
