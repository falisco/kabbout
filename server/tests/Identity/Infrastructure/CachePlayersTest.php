<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;
use Kabbout\Identity\Infrastructure\CachePlayers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;

final class CachePlayersTest extends TestCase
{
    private MockClock $clock;
    private WatchedCache $cache;
    private WatchedLocks $locks;
    private CachePlayers $players;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-18 12:00:00');
        $this->cache = new WatchedCache($this->clock);
        $this->locks = new WatchedLocks($this->cache);
        $this->players = new CachePlayers($this->cache, new LockFactory($this->locks), $this->clock);
    }

    public function testAPlayerTakenInIsRecognisedByTheProofOfTheirSecretAndByNoOther(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];

        self::assertTrue($this->players->admit($ana, $proof));

        self::assertEquals($ana, $this->players->seenAgain($proof));
        self::assertNull($this->players->seenAgain(Proof::of(Secret::random())));
    }

    public function testANameBelongsToOnePlayerAtATime(): void
    {
        [$ana, $hers] = [self::guest('C4821973'), Proof::of(Secret::random())];
        [$ben, $his] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $hers);

        self::assertFalse($this->players->admit($ben, $his));

        // nothing was kept of the one turned away, and the name is still the first one's
        self::assertNull($this->players->seenAgain($his));
        self::assertEquals($ana, $this->players->seenAgain($hers));
        self::assertTrue($this->players->admit(self::guest('C0000001'), $his));
    }

    public function testANameIsLookedUpAndTakenUnderOneLockSoThatTwoGuestsAtTheSameInstantAreToldApart(): void
    {
        $this->players->admit(self::guest('C4821973'), Proof::of(Secret::random()));
        self::assertSame(['lock', 'write', 'write', 'unlock'], $this->cache->journal);

        // turned away: the name was looked up under the lock too, and nothing was written
        $this->cache->journal = [];
        $this->players->admit(self::guest('C4821973'), Proof::of(Secret::random()));
        self::assertSame(['lock', 'unlock'], $this->cache->journal);
    }

    public function testWhatIsKeptOfASecretIsItsProof(): void
    {
        $secret = Secret::random();
        $this->players->admit(self::guest('C4821973'), Proof::of($secret));

        $kept = $this->cache->getValues();
        self::assertStringNotContainsString($secret->reveal(), serialize($kept));
        self::assertArrayHasKey('player.proof.'.Proof::of($secret)->toString(), $kept);
        self::assertArrayHasKey('player.name.C4821973', $kept);
    }

    public function testRecognisingAPlayerWritesNothingExceptTheFirstTimeOfADay(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $proof);
        $this->cache->journal = [];

        // the same day, however often: read, and no lock taken
        foreach (['+1 minute', '+6 hours', '+17 hours'] as $later) {
            $this->clock->modify($later);
            self::assertEquals($ana, $this->players->seenAgain($proof));
        }
        self::assertSame([], $this->cache->journal);
        // a secret nobody holds never takes the lock, whatever the day
        $this->players->seenAgain(Proof::of(Secret::random()));
        self::assertSame([], $this->cache->journal);

        // a day after it was last written: written again, under the lock, once
        $this->clock->modify('+1 hour');
        self::assertEquals($ana, $this->players->seenAgain($proof));
        self::assertEquals($ana, $this->players->seenAgain($proof));
        self::assertSame(['lock', 'write', 'write', 'unlock'], $this->cache->journal);
    }

    public function testAGuestIsKeptThirtyDaysFromTheLastTimeTheyWereSeen(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $proof);

        // seen every 29 days, a guest is never forgotten, and nobody else gets the name
        foreach ([1, 2, 3] as $visit) {
            $this->clock->modify('+29 days');
            self::assertEquals($ana, $this->players->seenAgain($proof), "visit $visit");
            self::assertFalse($this->players->admit(self::guest('C4821973'), Proof::of(Secret::random())), "visit $visit");
        }
        // last seen late in a day whose visit was already written: the thirty days count from then all the same
        $this->clock->modify('+23 hours');
        self::assertEquals($ana, $this->players->seenAgain($proof));
        $this->clock->modify('+30 days');
        self::assertEquals($ana, $this->players->seenAgain($proof));

        // unseen for thirty days and one more, the guest is nobody and the name is free again
        $this->clock->modify('+31 days +1 minute');
        self::assertNull($this->players->seenAgain($proof));
        self::assertTrue($this->players->admit(self::guest('C4821973'), Proof::of(Secret::random())));
    }

    public function testAGuestWhoseNameWentToSomebodyElseIsNobodyAndDoesNotTakeItBack(): void
    {
        [$ana, $hers] = [self::guest('C4821973'), Proof::of(Secret::random())];
        [$ben, $his] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $hers);
        // the name was let go a moment before what is kept under the proof
        $this->cache->deleteItem('player.name.C4821973');
        self::assertTrue($this->players->admit($ben, $his));

        self::assertNull($this->players->seenAgain($hers));
        // nor a day later, when being seen would have written the name again
        $this->clock->modify('+2 days');
        self::assertNull($this->players->seenAgain($hers));

        self::assertEquals($ben, $this->players->seenAgain($his));
    }

    public function testADiskThatTakesNoWriteLetsNobodyInAndSaysSo(): void
    {
        $proof = Proof::of(Secret::random());
        $this->cache->full = true;

        try {
            $this->players->admit(self::guest('C4821973'), $proof);
            self::fail('A guest came in whom the server did not keep.');
        } catch (PlayersUnavailable) {
            // the lock was given back, and once the disk takes writes again the name is free
            self::assertSame(['lock', 'unlock'], $this->cache->journal);
            $this->cache->full = false;
            self::assertNull($this->players->seenAgain($proof));
            self::assertTrue($this->players->admit(self::guest('C4821973'), $proof));
        }
    }

    public function testAPlayerAlreadyKnownIsStillRecognisedWhenTheirVisitCannotBeWritten(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $proof);
        $this->clock->modify('+2 days');
        $this->cache->full = true;

        self::assertEquals($ana, $this->players->seenAgain($proof));
    }

    public function testWhenTheLockCannotBeTakenThePlayersAreUnavailable(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $proof);
        $this->locks->broken = true;

        // what needs no lock still answers: a player seen today
        self::assertEquals($ana, $this->players->seenAgain($proof));

        $this->expectException(PlayersUnavailable::class);
        $this->players->admit(self::guest('C0000001'), Proof::of(Secret::random()));
    }

    public function testWhatThePoolHoldsThatIsNoPlayerIsNobody(): void
    {
        $proof = Proof::of(Secret::random());
        $key = 'player.proof.'.$proof->toString();
        $id = PlayerId::random()->toString();
        $now = $this->clock->now()->getTimestamp();
        $this->cache->save($this->cache->getItem('player.name.C4821973')->set($id));

        $notAPlayer = [
            'not a list',
            [],
            [$id],
            [$id, 'C4821973'],
            [$id, 'C4821973', 'guest'],
            [$id, 'C4821973', 'guest', 'today'],
            [$id, 'C4821973', 'king', $now],
            ['not an id', 'C4821973', 'guest', $now],
            [$id, 'no name', 'guest', $now],
            [7, 'C4821973', 'guest', $now],
        ];
        foreach ($notAPlayer as $kept) {
            $this->cache->save($this->cache->getItem($key)->set($kept));

            self::assertNull($this->players->seenAgain($proof), (string) json_encode($kept));
        }
        // and what is one, to be sure the others were refused for what they are
        $this->cache->save($this->cache->getItem($key)->set([$id, 'C4821973', 'guest', $now]));
        self::assertSame('C4821973', $this->players->seenAgain($proof)?->name->toString());
    }

    private static function guest(string $name): Player
    {
        return Player::guest(PlayerId::random(), PlayerName::fromString($name));
    }
}
