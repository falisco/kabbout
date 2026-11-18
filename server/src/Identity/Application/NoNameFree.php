<?php

declare(strict_types=1);

namespace Kabbout\Identity\Application;

/** Every name drawn for a new guest was another player's. */
final class NoNameFree extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Every name drawn for a new guest was another player’s.');
    }
}
