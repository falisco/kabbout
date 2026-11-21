<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Table\Table;

/** The table was the player's, and was left alone too long. */
final class TableExpired extends \RuntimeException implements TableRefusal
{
    public function __construct()
    {
        parent::__construct(\sprintf('This table has expired: nothing was played on it for %d hours.', intdiv(Table::SECONDS_OPEN, 3600)));
    }
}
