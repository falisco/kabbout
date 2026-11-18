<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

/** What time it is, for the tables: a port, so that a test can let three hours go by. */
interface Now
{
    /** @return int seconds, counted the way every clock of the server counts them */
    public function seconds(): int;
}
