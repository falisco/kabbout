<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

/**
 * What a model made of a sentence, taken at its word for nothing: the settings may not be a card, and
 * the pieces it says it could not place may not be the player's.
 */
final readonly class Reading
{
    /**
     * @param bool         $understood whether the sentence speaks of how to bid at all
     * @param array<mixed> $settings   what would be the card
     * @param list<string> $unplaced   the pieces of the sentence it found no setting for
     */
    public function __construct(public bool $understood, public array $settings, public array $unplaced)
    {
    }
}
