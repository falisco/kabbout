<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

enum ActionKind: string
{
    case Bid = 'bid';
    case Play = 'play';
    /** asks for the next deal, once one is over */
    case Next = 'next';
}
