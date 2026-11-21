<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\NetworkAddress;
use PHPUnit\Framework\TestCase;

final class NetworkAddressTest extends TestCase
{
    public function testEveryAddressOfAnIpv6BlockIsCountedAsOne(): void
    {
        $one = NetworkAddress::of('2001:db8:1:2:aaaa:bbbb:cccc:dddd');

        self::assertSame($one->key, NetworkAddress::of('2001:db8:1:2::1')->key);
        self::assertSame($one->key, NetworkAddress::of('2001:0db8:0001:0002:ffff:ffff:ffff:ffff')->key);
        self::assertNotSame($one->key, NetworkAddress::of('2001:db8:1:3::1')->key);
    }

    public function testAnIpv4AddressIsCountedOnItsOwn(): void
    {
        self::assertSame(NetworkAddress::of('203.0.113.7')->key, NetworkAddress::of('203.0.113.7')->key);
        self::assertNotSame(NetworkAddress::of('203.0.113.7')->key, NetworkAddress::of('203.0.113.8')->key);
        // written the IPv6 way, as some proxies pass it on, it is the same address and not everybody's
        self::assertSame(NetworkAddress::of('203.0.113.7')->key, NetworkAddress::of('::ffff:203.0.113.7')->key);
        self::assertNotSame(NetworkAddress::of('::ffff:203.0.113.7')->key, NetworkAddress::of('::ffff:203.0.113.8')->key);
    }

    public function testWhatIsNoAddressIsCountedAsOneAndIsNeverAnError(): void
    {
        $unknown = NetworkAddress::of(null)->key;

        self::assertSame($unknown, NetworkAddress::of('')->key);
        self::assertSame($unknown, NetworkAddress::of('not an address|with a pipe')->key);
        self::assertNotSame($unknown, NetworkAddress::of('203.0.113.7')->key);
    }
}
