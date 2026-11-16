<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Domain;

use Kabbout\Assistant\Domain\Visitor;
use PHPUnit\Framework\TestCase;

final class VisitorTest extends TestCase
{
    public function testEveryAddressOfAnIpv6BlockIsOneVisitor(): void
    {
        $one = Visitor::atAddress('2001:db8:1:2:aaaa:bbbb:cccc:dddd');

        self::assertSame($one->key, Visitor::atAddress('2001:db8:1:2::1')->key);
        self::assertSame($one->key, Visitor::atAddress('2001:0db8:0001:0002:ffff:ffff:ffff:ffff')->key);
        self::assertNotSame($one->key, Visitor::atAddress('2001:db8:1:3::1')->key);
    }

    public function testAnIpv4AddressIsAVisitorOfItsOwn(): void
    {
        self::assertSame(Visitor::atAddress('203.0.113.7')->key, Visitor::atAddress('203.0.113.7')->key);
        self::assertNotSame(Visitor::atAddress('203.0.113.7')->key, Visitor::atAddress('203.0.113.8')->key);
        // written the IPv6 way, as some proxies pass it on, it is the same visitor and not everybody's
        self::assertSame(Visitor::atAddress('203.0.113.7')->key, Visitor::atAddress('::ffff:203.0.113.7')->key);
        self::assertNotSame(Visitor::atAddress('::ffff:203.0.113.7')->key, Visitor::atAddress('::ffff:203.0.113.8')->key);
    }

    public function testWhatIsNoAddressIsOneVisitorAndNeverAnError(): void
    {
        $unknown = Visitor::atAddress(null)->key;

        self::assertSame($unknown, Visitor::atAddress('')->key);
        self::assertSame($unknown, Visitor::atAddress('not an address|with a pipe')->key);
        self::assertNotSame($unknown, Visitor::atAddress('203.0.113.7')->key);
    }
}
