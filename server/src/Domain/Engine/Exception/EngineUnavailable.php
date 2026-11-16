<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Exception;

/** The engine could not be asked, or answered something it should not: a failure of ours, never of the player. */
final class EngineUnavailable extends \RuntimeException implements EngineException
{
}
