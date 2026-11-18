<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/** Where a guest's name is drawn from. A port, so that a test can make chance repeat itself. */
interface Chance
{
    /** @return int a whole number from 0 to $limit - 1, each as likely as the others */
    public function below(int $limit): int;
}
