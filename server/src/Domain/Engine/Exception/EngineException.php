<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Exception;

/** Everything that can go wrong when the engine is asked: a refusal, a record that is not one, or no answer. */
interface EngineException extends \Throwable
{
}
