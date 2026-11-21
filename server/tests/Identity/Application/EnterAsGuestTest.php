<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Application\RecogniseGuest;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Tests\Identity\Domain\ListedPlayers;
use Kabbout\Tests\Identity\Domain\ScriptedChance;
use PHPUnit\Framework\TestCase;

/** Who comes in, under which name, and what proves it is them the next time. */
final class EnterAsGuestTest extends TestCase
{
    public function testAGuestGetsANameOfTheirOwnAndTheSecretThatProvesIt(): void
    {
        $players = new ListedPlayers();
        $chance = new ScriptedChance([4821973]);

        $entered = self::desk($players, $chance)(self::home(), null);

        self::assertNotNull($entered->secret);
        self::assertSame('C4821973', $entered->player->name->toString());
        self::assertSame([10_000_000], $chance->limits);
        self::assertSame($entered->player, (new RecogniseGuest($players))($entered->secret->reveal()));
        // what the players are known by is the proof of the secret, never the secret
        self::assertSame([Proof::of($entered->secret)->toString()], array_keys($players->known));
    }

    public function testWhoeverIsAPlayerAlreadyStaysWhoTheyAreAndIsHandedNoSecondSecret(): void
    {
        $players = new ListedPlayers();
        $entries = new CountingEntries();
        $enter = new EnterAsGuest($players, new ScriptedChance([5]), $entries);
        $ana = $enter(self::home(), null)->player;

        $again = $enter(self::home(), $ana);

        self::assertSame($ana, $again->player);
        self::assertNull($again->secret);
        // nobody was taken in, chance was not asked (the script held one draw), and no new guest was counted
        self::assertCount(1, $players->known);
        self::assertCount(1, $entries->from);
    }

    public function testANameAnotherPlayerHoldsIsDrawnAgain(): void
    {
        $players = new ListedPlayers();
        // chance repeats itself: the second guest draws the name of the first, then another
        $enter = self::desk($players, new ScriptedChance([5, 5, 7]));

        $first = $enter(self::home(), null);
        $second = $enter(self::home(), null);

        self::assertSame(['C0000005', 'C0000007'], [$first->player->name->toString(), $second->player->name->toString()]);
        self::assertNotSame($first->player->id->toString(), $second->player->id->toString());
        self::assertNotSame($first->secret?->reveal(), $second->secret?->reveal());
        self::assertCount(2, $players->known);
    }

    public function testChanceThatOnlyRepeatsItselfEndsInNoNameRatherThanInAWaitWithoutEnd(): void
    {
        $players = new ListedPlayers();
        // one draw for the first guest, then every draw the second is allowed
        $enter = self::desk($players, new ScriptedChance(array_fill(0, 1 + EnterAsGuest::DRAWS, 5)));
        $enter(self::home(), null);

        try {
            $enter(self::home(), null);
            self::fail('A guest came in under a name another player holds.');
        } catch (NoNameFree) {
            // nothing is kept of the guest who did not come in
            self::assertCount(1, $players->known);
        }
    }

    public function testWhenThePlayersCannotBeKeptNobodyComesIn(): void
    {
        $players = new ListedPlayers();
        $players->unavailable = true;

        $this->expectException(PlayersUnavailable::class);

        self::desk($players, new ScriptedChance([5]))(self::home(), null);
    }

    public function testNewGuestsAreCountedAgainstTheAddressTheyComeFromAndPastTheCountNobodyComesIn(): void
    {
        $players = new ListedPlayers();
        $entries = new CountingEntries(2);
        // two draws only: past the count, chance is not even asked
        $enter = new EnterAsGuest($players, new ScriptedChance([5, 6]), $entries);
        $enter(NetworkAddress::of('203.0.113.1'), null);
        $enter(NetworkAddress::of('203.0.113.2'), null);

        try {
            $enter(NetworkAddress::of('203.0.113.1'), null);
            self::fail('A guest came in past the count.');
        } catch (EnteredTooOften $tooOften) {
            self::assertSame(42, $tooOften->retryAfter);
            self::assertSame(['address:203.0.113.1', 'address:203.0.113.2'], $entries->from);
            self::assertCount(2, $players->known);
        }
    }

    private static function desk(ListedPlayers $players, ScriptedChance $chance): EnterAsGuest
    {
        return new EnterAsGuest($players, $chance, new CountingEntries());
    }

    private static function home(): NetworkAddress
    {
        return NetworkAddress::of('203.0.113.1');
    }
}
