<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/**
 * The rule the coach is held to: the engine computes, the model talks. Every number and every card of
 * an answer must be found in a tool result of the conversation. No number is let through on its own
 * merits, not even a small one, and a trick the answer points at must be one a tool looked at.
 *
 * What it cannot see: a rank or a suit named alone ("your ace", "in trumps"), and numbers written in
 * words beyond the ones NumberWords knows. The prompt asks for digits and for whole card names.
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
