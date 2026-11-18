<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Application\RecogniseGuest;
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

        $entered = (new EnterAsGuest($players, $chance))(null);

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
        $enter = new EnterAsGuest($players, new ScriptedChance([5]));
        $ana = $enter(null)->player;

        $again = $enter($ana);

        self::assertSame($ana, $again->player);
        self::assertNull($again->secret);
        // nobody was taken in, and chance was not asked: the script held one draw only
        self::assertCount(1, $players->known);
    }

    public function testANameAnotherPlayerHoldsIsDrawnAgain(): void
    {
        $players = new ListedPlayers();
        // chance repeats itself: the second guest draws the name of the first, then another
        $enter = new EnterAsGuest($players, new ScriptedChance([5, 5, 7]));

        $first = $enter(null);
        $second = $enter(null);

        self::assertSame(['C0000005', 'C0000007'], [$first->player->name->toString(), $second->player->name->toString()]);
        self::assertNotSame($first->player->id->toString(), $second->player->id->toString());
        self::assertNotSame($first->secret?->reveal(), $second->secret?->reveal());
        self::assertCount(2, $players->known);
    }

    public function testChanceThatOnlyRepeatsItselfEndsInNoNameRatherThanInAWaitWithoutEnd(): void
    {
        $players = new ListedPlayers();
        // one draw for the first guest, then every draw the second is allowed
        $enter = new EnterAsGuest($players, new ScriptedChance(array_fill(0, 1 + EnterAsGuest::DRAWS, 5)));
        $enter(null);

        try {
            $enter(null);
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

        (new EnterAsGuest($players, new ScriptedChance([5])))(null);
    }
}
