<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/**
 * Where a question comes from, for what is counted: the questions to the coach, per minute and per
 * deal. A visitor is a network address, and not a player: a guest costs nothing to make, so a count
 * per guest would be no limit at all. The counts move to the player when players have accounts.
 * What the server keeps for somebody, the coach's memory of a deal to begin with, is kept under the
 * player (Identity), never under a visitor.
 */
final readonly class Visitor
{
    /** an IPv6 site is handed a block of this many leading bits: all of its addresses are one visitor */
    private const int IPV6_PREFIX_BYTES = 8;

    /** how an IPv4 address starts when written as an IPv6 one (::ffff:a.b.c.d) */
    private const string IPV4_IN_IPV6 = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    private function __construct(public string $key)
    {
    }

    /**
     * The visitor behind a network address. Every address of one IPv6 block (/64) is the same visitor:
     * whoever holds the block can take a new address at will. What is no address at all is one visitor too.
     */
    public static function atAddress(?string $address): self
    {
        $packed = null === $address ? false : @inet_pton($address);
        if (false === $packed) {
            return new self('address:unknown');
        }
        if (16 === \strlen($packed) && str_starts_with($packed, self::IPV4_IN_IPV6)) {
            // an IPv4 address written the IPv6 way is that IPv4 address, and no block of anyone's
            $packed = substr($packed, \strlen(self::IPV4_IN_IPV6));
        }
        if (16 === \strlen($packed)) {
            $packed = substr($packed, 0, self::IPV6_PREFIX_BYTES).str_repeat("\0", 16 - self::IPV6_PREFIX_BYTES);
        }

        return new self('address:'.inet_ntop($packed));
    }
}
