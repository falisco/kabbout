<?php

declare(strict_types=1);

namespace Kabbout\Domain\Engine\Enum;

/** How a deal is counted: the federation's way (the value bid is added), or the Tunisian cafés' (it never is). */
enum Scoring: string
{
    case Ffb = 'ffb';
    case Tunisian = 'tunisian';
}
