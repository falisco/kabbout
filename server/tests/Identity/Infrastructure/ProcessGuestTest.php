<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Infrastructure\ProcessGuest;
use Kabbout\Identity\Infrastructure\Security\SecurityCurrentPlayer;
use Kabbout\Tests\Identity\Application\CountingEntries;
use Kabbout\Tests\Identity\Domain\ListedPlayers;
use Kabbout\Tests\Identity\Domain\ScriptedChance;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

final class ProcessGuestTest extends TestCase
{
    public function testAProcessWithNoRequestIsOneGuestMadeWhenSomebodyFirstAsksWhoItIs(): void
    {
        $players = new ListedPlayers();
        $entries = new CountingEntries();
        $guest = self::guest($players, $entries, new RequestStack());

        // whoever only looks at what the server offers makes no guest
        self::assertCount(0, $players->known);

        $who = $guest->player();
        self::assertNotNull($who);
        self::assertSame('C0000007', $who->name->toString());
        self::assertSame($who, $guest->player());
        self::assertCount(1, $players->known);
        // no request, no address: counted against the address that is none
        self::assertSame(['address:unknown'], $entries->from);
    }

    public function testOverHttpItIsWhoeverSecurityRecognisedAndNobodyIsMadeAGuestForAsking(): void
    {
        $players = new ListedPlayers();
        $requests = new RequestStack();
        $requests->push(Request::create('/mcp'));
        $guest = self::guest($players, new CountingEntries(), $requests);

        self::assertNull($guest->player());
        self::assertCount(0, $players->known);
    }

    private static function guest(ListedPlayers $players, CountingEntries $entries, RequestStack $requests): ProcessGuest
    {
        // Security with nobody behind it: an empty token storage
        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());
        $security = new SecurityCurrentPlayer(new Security($container));

        return new ProcessGuest($security, new EnterAsGuest($players, new ScriptedChance([7, 8]), $entries), $requests);
    }
}
