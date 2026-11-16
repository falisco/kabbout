<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Enum;

enum Phase: string
{
    case Bidding = 'bidding';
    case Playing = 'playing';
    case RoundEnd = 'roundEnd';
    case GameEnd = 'gameEnd';
}
