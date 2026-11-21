<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

/** What was sent is no sentence to read: empty, or longer than a player would say. The sender's mistake. */
final class InvalidSentence extends \InvalidArgumentException
{
}
