<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Infrastructure;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;
use Kabbout\Identity\Infrastructure\CachePlayers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class CachePlayersTest extends TestCase
{
    private MockClock $clock;
    private ArrayAdapter $cache;
    private CachePlayers $players;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-18 12:00:00');
        $this->cache = new ArrayAdapter(clock: $this->clock);
        $this->players = new CachePlayers($this->cache, new LockFactory(new InMemoryStore()));
    }

    public function testAPlayerTakenInIsRecognisedByTheProofOfTheirSecretAndByNoOther(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];

        self::assertTrue($this->players->admit($ana, $proof));

        self::assertEquals($ana, $this->players->provenBy($proof));
        self::assertNull($this->players->provenBy(Proof::of(Secret::random())));
    }

    public function testANameBelongsToOnePlayerAtATime(): void
    {
        [$ana, $hers] = [self::guest('C4821973'), Proof::of(Secret::random())];
        [$ben, $his] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $hers);

        self::assertFalse($this->players->admit($ben, $his));

        // nothing was kept of the one turned away, and the name is still the first one's
        self::assertNull($this->players->provenBy($his));
        self::assertEquals($ana, $this->players->provenBy($hers));
        self::assertTrue($this->players->admit(self::guest('C0000001'), $his));
    }

    public function testWhatIsKeptOfASecretIsItsHash(): void
    {
        $secret = Secret::random();
        $this->players->admit(self::guest('C4821973'), Proof::of($secret));

        $kept = $this->cache->getValues();
        self::assertStringNotContainsString($secret->reveal(), serialize($kept));
        self::assertArrayHasKey('player.proof.'.hash('sha256', $secret->reveal()), $kept);
        self::assertArrayHasKey('player.name.C4821973', $kept);
    }

    public function testAGuestIsKeptThirtyDaysFromTheLastTimeTheyWereSeen(): void
    {
        [$ana, $proof] = [self::guest('C4821973'), Proof::of(Secret::random())];
        $this->players->admit($ana, $proof);

        // seen every 29 days, a guest is never forgotten, and nobody else gets the name
        foreach ([1, 2, 3] as $visit) {
            $this->clock->modify('+29 days');
            self::assertEquals($ana, $this->players->provenBy($proof), "visit $visit");
            self::assertFalse($this->players->admit(self::guest('C4821973'), Proof::of(Secret::random())), "visit $visit");
        }

        // unseen for 30 days and a little more, the guest is nobody and the name is free again
        $this->clock->modify('+30 days +1 minute');
        self::assertNull($this->players->provenBy($proof));
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

        self::assertNull($this->players->provenBy($hers));

        self::assertEquals($ben, $this->players->provenBy($his));
    }

    public function testWhatThePoolHoldsThatIsNoPlayerIsNobody(): void
    {
        $secret = Secret::random();
        $proof = Proof::of($secret);
        $key = 'player.proof.'.$proof->toString();
        $id = PlayerId::random()->toString();

        foreach (['not a list', [], [$id], [$id, 'C4821973'], [$id, 'C4821973', 'king'], ['not an id', 'C4821973', 'guest'], [$id, 'no name', 'guest'], [7, 'C4821973', 'guest']] as $kept) {
            $this->cache->save($this->cache->getItem($key)->set($kept));

            self::assertNull($this->players->provenBy($proof), (string) json_encode($kept));
        }
    }

    private static function guest(string $name): Player
    {
        return Player::guest(PlayerId::random(), PlayerName::fromString($name));
    }
}
