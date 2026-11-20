<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Table;

/** The tables cannot be read or written. */
final class TablesUnavailable extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('The tables cannot be kept right now.', previous: $previous);
    }
}
