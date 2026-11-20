<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

/**
 * No table of that name for whoever asks. A name nobody was given, a name that is no name at all and
 * the table of another player get this same answer: whoever tries names learns nothing from it.
 */
final class NoSuchTable extends \RuntimeException implements TableRefusal
{
    public function __construct()
    {
        parent::__construct('There is no such table.');
    }
}
