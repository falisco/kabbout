<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/**
 * The rule the coach is held to: the engine computes, the model talks. Every number and every card of
 * an answer must be found in a tool result of the conversation. No number is let through on its own
 * merits, not even a small one, and a trick the answer points at must be one a tool looked at.
 *
 * What it cannot see, and the prompt asks the model not to write:
 * - a rank or a suit named alone ("your ace", "in trumps");
 * - numbers written in words beyond the ones NumberWords knows ("eleven", "cent soixante");
 * - what a number is said of: the numbers of the tool results are one set, so a true number given to
 *   the wrong side ("you lost 250" when 250 is the other team's score) passes.
 * In French and English a card is read only with "de" or "of" in it, or as its code or symbol; a card
 * written otherwise ("ace hearts") is not checked.
 * It reads too much rather than too little where the two cannot be told apart: "AS" in capitals is the
 * ace of spades, and a decimal ("52.0") is two numbers. A good answer sent back costs one more try.
 */
final class GroundingGuard
{
    /** @return list<string> what the answer states without a tool result behind it, as written for the model: numbers in digits, cards as codes, tricks as "trick 7"; empty when the answer holds */
    public static function ungrounded(string $answer, Facts $facts): array
    {
        $reading = Reading::of($answer);
        $missing = [];
        foreach ($reading->numbers as $number) {
            if (!$facts->hasNumber($number)) {
                $missing[] = (string) $number;
            }
        }
        // a trick the answer points at is one a tool looked at: a 7 seen on a card does not make a seventh trick known
        foreach ($reading->tricks as $trick) {
            if (!$facts->hasTrick($trick)) {
                $missing[] = 'trick '.$trick;
            }
        }
        foreach ($reading->cards as $card) {
            if (!$facts->hasCard($card)) {
                $missing[] = $card;
            }
        }

        return array_values(array_unique($missing));
    }
}
