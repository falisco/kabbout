<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/** Somebody the server knows: who they are for it, the name they go by, and how they came in. */
final readonly class Player
{
    public function __construct(public PlayerId $id, public PlayerName $name, public PlayerKind $kind)
    {
    }

    public static function guest(PlayerId $id, PlayerName $name): self
    {
        return new self($id, $name, PlayerKind::Guest);
    }
}
