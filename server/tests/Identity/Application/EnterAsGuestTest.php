<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Application\RecogniseVisitor;
use PHPUnit\Framework\TestCase;

/** Who comes in, under which name, and what proves it is them the next time. */
final class EnterAsGuestTest extends TestCase
{
    public function testAGuestGetsANameOfTheirOwnAndTheSecretThatProvesIt(): void
    {
        $players = new ListedPlayers();
        $chance = new ScriptedChance([4821973]);

        $entered = (new EnterAsGuest($players, $chance))();

        self::assertSame('C4821973', $entered->player->name->toString());
        self::assertSame([10_000_000], $chance->limits);
        self::assertSame($entered->player, (new RecogniseVisitor($players))($entered->secret->reveal()));
        // what the players are known by is a hash of the secret, never the secret
        self::assertSame([hash('sha256', $entered->secret->reveal())], array_keys($players->known));
    }

    public function testANameAnotherPlayerHoldsIsDrawnAgain(): void
    {
        $players = new ListedPlayers();
        // chance repeats itself: the second guest draws the name of the first, then another
        $enter = new EnterAsGuest($players, new ScriptedChance([5, 5, 7]));

        $first = $enter();
        $second = $enter();

        self::assertSame(['C0000005', 'C0000007'], [$first->player->name->toString(), $second->player->name->toString()]);
        self::assertFalse($first->player->id->equals($second->player->id));
        self::assertNotSame($first->secret->reveal(), $second->secret->reveal());
        self::assertCount(2, $players->known);
    }

    public function testChanceThatOnlyRepeatsItselfEndsInNoNameRatherThanInAWaitWithoutEnd(): void
    {
        $players = new ListedPlayers();
        $enter = new EnterAsGuest($players, new ScriptedChance(array_fill(0, 21, 5)));
        $enter();

        try {
            $enter();
            self::fail('A guest came in under a name another player holds.');
        } catch (NoNameFree) {
            // nothing is kept of the guest who did not come in
            self::assertCount(1, $players->known);
        }
    }
}
