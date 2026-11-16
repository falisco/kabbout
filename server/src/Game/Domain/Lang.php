<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/** The languages of the game: French, English, Tunisian Arabic. */
enum Lang: string
{
    case Fr = 'fr';
    case En = 'en';
    case Ar = 'ar';
}
