<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure;

use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayerKind;
use Kabbout\Identity\Domain\PlayerName;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Domain\Proof;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockFailed;
use Symfony\Component\Lock\LockFactory;

/**
 * The players in a cache pool of their own, on disk. A player is two entries: under the proof of their
 * secret, who they are and when that was last written; under their name, their id. The entry under the
 * name is what says whose a name is: a player is only recognised while their name is still theirs.
 *
 * Recognising a player reads, and writes nothing, except the first time of a day: both entries are then
 * written again, and expire together. They are kept a day longer than Players::DAYS_KEPT, so that a guest
 * last seen at the end of such a day still has the days promised.
 *
 * A name is taken, and kept, under one lock: two guests drawing the same name at the same instant are
 * told apart, and a name freed by time is not taken back by the guest who let it go.
 */
final readonly class CachePlayers implements Players
{
    private const int DAY = 24 * 3600;
    private const int SECONDS_KEPT = (Players::DAYS_KEPT + 1) * self::DAY;
    private const string LOCK = 'identity.players';

    public function __construct(
        #[Autowire(service: 'cache.players')] private CacheItemPoolInterface $cache,
        private LockFactory $locks,
        private ClockInterface $clock,
    ) {
    }

    public function admit(Player $player, Proof $proof): bool
    {
        return $this->alone(function () use ($player, $proof): bool {
            if ($this->cache->hasItem(self::nameKey($player->name))) {
                return false;
            }
            // a disk that is full or closed to the server says so here: nobody comes in under a name that was not kept
            if (!$this->keep($player, $proof)) {
                throw new PlayersUnavailable();
            }

            return true;
        });
    }

    public function seenAgain(Proof $proof): ?Player
    {
        [$player, $written] = $this->keptUnder($proof);
        if (null === $player || !$this->holdsTheirName($player)) {
            return null;
        }
        if ($this->clock->now()->getTimestamp() - $written < self::DAY) {
            return $player;
        }

        return $this->alone(function () use ($proof): ?Player {
            // read now that nothing can change: the name may have gone to somebody else
            [$player] = $this->keptUnder($proof);
            if (null === $player || !$this->holdsTheirName($player)) {
                return null;
            }
            // if this cannot be written the player is who they were, kept as long as they were
            $this->keep($player, $proof);

            return $player;
        });
    }

    /**
     * @template T
     *
     * @param \Closure(): T $do what must not happen twice at once
     *
     * @return T
     *
     * @throws PlayersUnavailable when the lock cannot be taken
     */
    private function alone(\Closure $do): mixed
    {
        try {
            $lock = $this->locks->createLock(self::LOCK);
            $lock->acquire(true);
            try {
                return $do();
            } finally {
                $lock->release();
            }
        } catch (LockFailed $failed) {
            throw new PlayersUnavailable($failed);
        }
    }

    /** @return array{Player|null, int} the player kept under a proof, and when that was written; what the pool holds that is no player is nobody */
    private function keptUnder(Proof $proof): array
    {
        $kept = $this->cache->getItem(self::proofKey($proof))->get();
        if (!\is_array($kept) || !\is_string($kept[0] ?? null) || !\is_string($kept[1] ?? null) || !\is_string($kept[2] ?? null) || !\is_int($kept[3] ?? null)) {
            return [null, 0];
        }
        $kind = PlayerKind::tryFrom($kept[2]);
        try {
            return [null === $kind ? null : new Player(PlayerId::fromString($kept[0]), PlayerName::fromString($kept[1]), $kind), $kept[3]];
        } catch (\InvalidArgumentException) {
            return [null, 0];
        }
    }

    private function holdsTheirName(Player $player): bool
    {
        return $this->cache->getItem(self::nameKey($player->name))->get() === $player->id->toString();
    }

    /** @return bool false when one of the two entries could not be written */
    private function keep(Player $player, Proof $proof): bool
    {
        $who = $this->cache->getItem(self::proofKey($proof));
        $who->set([$player->id->toString(), $player->name->toString(), $player->kind->value, $this->clock->now()->getTimestamp()]);
        $who->expiresAfter(self::SECONDS_KEPT);

        $name = $this->cache->getItem(self::nameKey($player->name));
        $name->set($player->id->toString());
        $name->expiresAfter(self::SECONDS_KEPT);

        return $this->cache->save($who) && $this->cache->save($name);
    }

    private static function proofKey(Proof $proof): string
    {
        return 'player.proof.'.$proof->toString();
    }

    private static function nameKey(PlayerName $name): string
    {
        return 'player.name.'.$name->toString();
    }
}
