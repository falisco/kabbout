<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * What a guest holds to be recognised: 256 random bits, given once to whoever enters and never kept by
 * the server, which keeps a Proof of it instead. reveal() is the way out, for the one who hands it over.
 *
 * The rest is made hard to do by accident. A secret has no public property and is no string by itself;
 * a dump of it (var_dump, print_r) and its JSON show nothing; it refuses to be serialised, so it ends in
 * no cache, queue or session. var_export is the one dump PHP gives a class no say in.
 */
final readonly class Secret
{
    private const string PATTERN = '/^[0-9a-f]{64}$/D';

    private function __construct(#[\SensitiveParameter] private string $hex)
    {
    }

    public static function random(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    /** What somebody presents as their secret, if it can be one at all. */
    public static function tryFrom(#[\SensitiveParameter] string $presented): ?self
    {
        return 1 === preg_match(self::PATTERN, $presented) ? new self($presented) : null;
    }

    public function reveal(): string
    {
        return $this->hex;
    }

    /** @return array{} */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('A secret is not kept anywhere: keep its proof.');
    }
}
