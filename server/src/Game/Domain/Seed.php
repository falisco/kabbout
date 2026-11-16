<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * What the engine draws from: the same seed always gives the same cards. A seed is 128 to 256 bits,
 * so that nobody can try them all against the cards of one hand and find the three others.
 *
 * Two rules for whoever holds one. A seed that deals cards is a secret of the server, like the hands
 * it deals. And a seed is used for one call only: used twice, it deals the same cards twice. A game
 * therefore keeps one secret seed, made by random(), and derives from it a new one for each call,
 * named after what the call is for ("move 17", "deal 3").
 */
final readonly class Seed
{
    private const string PATTERN = '/^[0-9a-f]{32,64}$/D';

    private function __construct(private string $hex)
    {
    }

    /** A new secret seed of 256 bits. */
    public static function random(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    /** @param string $hex 32 to 64 lower-case hexadecimal digits */
    public static function fromHex(#[\SensitiveParameter] string $hex): self
    {
        return 1 === preg_match(self::PATTERN, $hex) ? new self($hex) : throw new \InvalidArgumentException('A seed is 32 to 64 hexadecimal digits.');
    }

    /**
     * The seed of one use, which tells nothing of this one nor of the seeds of other uses.
     * The same purpose gives the same seed: that is what makes an analysis repeatable.
     */
    public function derive(string $purpose): self
    {
        return new self(hash_hmac('sha256', $purpose, $this->hex));
    }

    public function toHex(): string
    {
        return $this->hex;
    }
}
