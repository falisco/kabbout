<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/** The four suits, by the letter the engine writes them with: a card is a rank then a suit, as in "JS". */
enum Suit: string
{
    case Spades = 'S';
    case Hearts = 'H';
    case Diamonds = 'D';
    case Clubs = 'C';
}
