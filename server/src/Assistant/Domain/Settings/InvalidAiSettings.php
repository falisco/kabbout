<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Settings;

/** The AI was switched on with settings that cannot work. Its message never holds the key. */
final class InvalidAiSettings extends \DomainException
{
}
