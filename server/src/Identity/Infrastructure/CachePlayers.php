<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerKind;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\Proof;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * The players in a cache pool of their own, on disk. A player is two entries: under the proof of their
 * secret, who they are; under their name, their id. The entry under the name is what says whose a name
 * is: a player is only recognised while their name is still theirs. Both entries are written again each
 * time the player is seen, and expire together DAYS_KEPT days after the last time.
 *
 * A name is taken, and kept, under one lock: two guests drawing the same name at the same instant are
 * told apart, and a name freed by time is not taken back by the guest who let it go. A proof nobody
 * holds is turned away before the lock.
 */
final readonly class CachePlayers implements Players
{
    private const int SECONDS_KEPT = self::DAYS_KEPT * 24 * 3600;
    private const string LOCK = 'identity.players';

    public function __construct(#[Autowire(service: 'cache.players')] private CacheItemPoolInterface $cache, private LockFactory $locks)
    {
    }

    public function admit(Player $player, Proof $proof): bool
    {
        $lock = $this->locks->createLock(self::LOCK);
        $lock->acquire(true);
        try {
            if ($this->cache->hasItem(self::underName($player->name))) {
                return false;
            }
            $this->keep($player, $proof);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function provenBy(Proof $proof): ?Player
    {
        if (!$this->cache->hasItem(self::underProof($proof))) {
            return null;
        }
        $lock = $this->locks->createLock(self::LOCK);
        $lock->acquire(true);
        try {
            // read now that nothing can change: the name may have gone to somebody else
            $player = $this->under($proof);
            if (null === $player || $this->cache->getItem(self::underName($player->name))->get() !== $player->id->toString()) {
                return null;
            }
            $this->keep($player, $proof);

            return $player;
        } finally {
            $lock->release();
        }
    }

    /** the player kept under a proof; what the pool holds that is no player is nobody */
    private function under(Proof $proof): ?Player
    {
        $kept = $this->cache->getItem(self::underProof($proof))->get();
        if (!\is_array($kept) || !\is_string($kept[0] ?? null) || !\is_string($kept[1] ?? null) || !\is_string($kept[2] ?? null)) {
            return null;
        }
        $kind = PlayerKind::tryFrom($kept[2]);
        try {
            return null === $kind ? null : new Player(PlayerId::fromString($kept[0]), PlayerName::fromString($kept[1]), $kind);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function keep(Player $player, Proof $proof): void
    {
        $who = $this->cache->getItem(self::underProof($proof));
        $who->set([$player->id->toString(), $player->name->toString(), $player->kind->value]);
        $who->expiresAfter(self::SECONDS_KEPT);
        $this->cache->save($who);

        $name = $this->cache->getItem(self::underName($player->name));
        $name->set($player->id->toString());
        $name->expiresAfter(self::SECONDS_KEPT);
        $this->cache->save($name);
    }

    private static function underProof(Proof $proof): string
    {
        return 'player.proof.'.$proof->toString();
    }

    private static function underName(PlayerName $name): string
    {
        return 'player.name.'.$name->toString();
    }
}
