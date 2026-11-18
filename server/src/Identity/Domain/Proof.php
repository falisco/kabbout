<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * What the server keeps of a secret: its SHA-256, from which the secret cannot be found again. It is
 * only ever made from a Secret, so what is stored is a hash by construction. A plain hash is enough
 * here, where a password would need a slow one: the secret is 256 random bits, and nobody can guess those.
 */
final readonly class Proof
{
    private function __construct(private string $hash)
    {
    }

    public static function of(Secret $secret): self
    {
        return new self(hash('sha256', $secret->reveal()));
    }

    /** 64 lower-case hexadecimal digits */
    public function toString(): string
    {
        return $this->hash;
    }
}
