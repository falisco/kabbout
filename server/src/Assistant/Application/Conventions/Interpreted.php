<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

use Kabbout\Game\Domain\ConventionCard;

/** A sentence turned into a card, with the pieces of the sentence the card has no place for. */
final readonly class Interpreted
{
    /** @param list<string> $unplaced pieces of the player's own sentence, each found in it */
    public function __construct(public ConventionCard $card, public array $unplaced)
    {
    }
}
