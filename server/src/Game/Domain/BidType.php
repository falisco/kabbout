<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/** What a seat may say during the auction. */
enum BidType: string
{
    case Pass = 'pass';
    case Contre = 'contre';
    case Surcontre = 'surcontre';
    /** a contract: a value and a trump suit */
    case Raise = 'bid';
}
