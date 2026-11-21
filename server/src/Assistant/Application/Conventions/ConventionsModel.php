<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

use Kabbout\Game\Domain\Lang;

/**
 * What reads a player's sentence about how they and their partner bid. It answers with a reading:
 * settings for a card, and what it could not place. It decides nothing: whether the settings make a
 * card is the domain's business, and what the player is shown of the rest is InterpretConventions'.
 */
interface ConventionsModel
{
    /**
     * @param string $sentence the player's own words, in any language they write in
     * @param Lang   $lang     the language of their screen, which is often but not always that of the sentence
     *
     * @throws ConventionsModelFailed when no reading can be had
     */
    public function read(string $sentence, Lang $lang): Reading;
}
