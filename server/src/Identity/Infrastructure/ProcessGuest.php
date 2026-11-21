<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure;

use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Kabbout\Identity\Infrastructure\Security\SecurityCurrentPlayer;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Who is asking, for a door that is open both over HTTP and to a process started for one client (the
 * MCP server over stdio). Over HTTP it is whoever Symfony Security recognised, or nobody. A process
 * with no request is one client from its start to its end: it comes in as a guest the first time
 * somebody asks who it is, and stays that guest. Nothing is asked of whoever only looks at what the
 * server offers, so they make no guest.
 *
 * The guest's secret is dropped: nothing presents it again, and the process is the proof.
 */
final class ProcessGuest implements CurrentPlayer
{
    private ?Player $entered = null;

    public function __construct(
        private readonly SecurityCurrentPlayer $recognised,
        private readonly EnterAsGuest $enter,
        private readonly RequestStack $requests,
    ) {
    }

    /**
     * @throws EnteredTooOften
     * @throws NoNameFree
     * @throws PlayersUnavailable
     */
    public function player(): ?Player
    {
        if (null !== $this->requests->getCurrentRequest()) {
            return $this->recognised->player();
        }

        // no request, so no address either: every such process is counted against the one address that is none
        return $this->entered ??= ($this->enter)(NetworkAddress::of(null), null)->player;
    }
}
