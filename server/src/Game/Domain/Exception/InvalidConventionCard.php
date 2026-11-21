<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Exception;

/** What was given as a conventions card is not one: a setting missing or unknown, a value the card does not allow. */
final class InvalidConventionCard extends \RuntimeException implements EngineException
{
}
