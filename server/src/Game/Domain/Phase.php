<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

enum Phase: string
{
    case Bidding = 'bidding';
    case Playing = 'playing';
    case RoundEnd = 'roundEnd';
    case GameEnd = 'gameEnd';
}
