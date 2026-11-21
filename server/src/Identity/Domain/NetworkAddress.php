<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * Where a request comes from, on the network. It is the one thing known of whoever asks before they are
 * a player, and the one thing a guest cannot change at will, so it is what is counted against: the
 * questions to the coach, the guests who come in. A guest costs nothing to make: a count per guest
 * would be no limit at all. The counts move to the player when players have accounts.
 *
 * What the server keeps for somebody is kept under the player, never under an address.
 */
final readonly class NetworkAddress
{
    /** an IPv6 site is handed a block of this many leading bits: all of its addresses are one visitor */
    private const int IPV6_PREFIX_BYTES = 8;

    /** how an IPv4 address starts when written as an IPv6 one (::ffff:a.b.c.d) */
    private const string IPV4_IN_IPV6 = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    private function __construct(public string $key)
    {
    }

    /**
     * The address as it is counted against. Every address of one IPv6 block (/64) is the same one:
     * whoever holds the block can take a new address at will. What is no address at all is one address too.
     */
    public static function of(?string $address): self
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
