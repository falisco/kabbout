<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure;

use Kabbout\Identity\Domain\Chance;

/** Chance as the system gives it, from the generator PHP uses for secrets. */
final readonly class SystemChance implements Chance
{
    public function below(int $limit): int
    {
        return random_int(0, $limit - 1);
    }
}
