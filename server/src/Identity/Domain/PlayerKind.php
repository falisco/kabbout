<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/** How a player came in. Today everybody is a guest; a member, who signs in, will be a second kind. */
enum PlayerKind: string
{
    case Guest = 'guest';
}
