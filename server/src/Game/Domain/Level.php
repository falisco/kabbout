<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/** How well a robot plays its cards. All levels bid alike. */
enum Level: string
{
    case Easy = 'easy';
    case Normal = 'normal';
    case Expert = 'expert';
}
