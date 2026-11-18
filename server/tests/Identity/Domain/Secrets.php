<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

/** Secrets for tests. */
final class Secrets
{
    /** A secret that differs from a real one by its last digit only: all the rest of it is the real one's. */
    public static function altered(string $secret): string
    {
        return substr($secret, 0, -1).('0' === $secret[-1] ? '1' : '0');
    }
}
