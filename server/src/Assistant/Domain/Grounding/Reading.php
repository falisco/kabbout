<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/** What an answer states that can be checked: its numbers, the cards it names, and the tricks it points at. */
final readonly class Reading
{
    private const string TRICK = '(?:plis?|tricks?|البلي|بلي)';

    /**
     * @param list<int>    $numbers every number, card ranks left out, in the order written
     * @param list<string> $cards   every card named, as codes
     * @param list<int>    $tricks  the numbers written next to the word for a trick
     */
    private function __construct(public array $numbers, public array $cards, public array $tricks)
    {
    }

    public static function of(string $answer): self
    {
        [$cards, $rest] = CardVocabulary::take($answer);
        $rest = NumberWords::toDigits($rest);

        preg_match_all('/\d+/', $rest, $found);
        $numbers = array_map(intval(...), $found[0]);

        $tricks = [];
        $after = '/'.self::TRICK.'\s*(?:n[°o]\.?\s*|numéro\s+|number\s+|رقم\s+|#\s*)?(\d+)/u';
        // "the 4th trick" points at a trick; "4 tricks" counts them
        $before = '/(\d+)\s*(?:ère|ere|er|re|ème|eme|e|st|nd|rd|th)?\s+(?:pli|trick|البلي|بلي)(?![\p{L}])/u';
        foreach ([$after, $before] as $pattern) {
            preg_match_all($pattern, $rest, $found);
            foreach ($found[1] as $number) {
                $tricks[] = (int) $number;
            }
        }
        $tricks = array_values(array_unique($tricks));
        sort($tricks);

        return new self($numbers, array_values(array_unique($cards)), $tricks);
    }
}
