<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Exception;

/** What was given as a bid is none: a raise that names no value or no suit. The message says what one is, and may be shown. */
final class InvalidBid extends \DomainException
{
}
