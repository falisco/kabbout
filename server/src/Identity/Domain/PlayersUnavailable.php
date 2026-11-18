<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/** What holds the players cannot be read or written right now: nobody can be taken in, and nobody is recognised. */
final class PlayersUnavailable extends \RuntimeException
{
    public function __construct(?\Throwable $because = null)
    {
        parent::__construct('The players cannot be read or written right now.', previous: $because);
    }
}
