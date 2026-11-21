<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

/** The sentence gave no card: it speaks of something else, or what was read from it is not a card. */
final class NotUnderstood extends \RuntimeException
{
}
