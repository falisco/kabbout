<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/**
 * How the thirty-two cards are called in the three languages of the game, with the words of the screen
 * (app/web/src/i18n). Used both ways: to name a card in a text, and to find the cards a text names.
 * A card is written here as the engine writes it: rank then suit, as in "QH".
 */
final class CardVocabulary
{
    /** the names the screen shows, by language, then by rank or suit */
    private const array RANKS = [
        'fr' => ['7' => '7', '8' => '8', '9' => '9', 'T' => '10', 'J' => 'valet', 'Q' => 'dame', 'K' => 'roi', 'A' => 'as'],
        'en' => ['7' => '7', '8' => '8', '9' => '9', 'T' => '10', 'J' => 'jack', 'Q' => 'queen', 'K' => 'king', 'A' => 'ace'],
        'ar' => ['7' => '7', '8' => '8', '9' => '9', 'T' => '10', 'J' => 'الفالي', 'Q' => 'الدامة', 'K' => 'الروا', 'A' => 'اللاص'],
    ];
    private const array SUITS = [
        'fr' => ['S' => 'pique', 'H' => 'cœur', 'D' => 'carreau', 'C' => 'trèfle'],
        'en' => ['S' => 'spades', 'H' => 'hearts', 'D' => 'diamonds', 'C' => 'clubs'],
        'ar' => ['S' => 'بيك', 'H' => 'كور', 'D' => 'كارو', 'C' => 'سباتة'],
    ];

    /** every way a rank may be written in a sentence, lower case, to the rank */
    private const array RANK_WORDS = [
        '7' => '7', '8' => '8', '9' => '9', '10' => 'T',
        'sept' => '7', 'huit' => '8', 'neuf' => '9', 'dix' => 'T', 'valet' => 'J', 'dame' => 'Q', 'roi' => 'K', 'as' => 'A',
        'seven' => '7', 'eight' => '8', 'nine' => '9', 'ten' => 'T', 'jack' => 'J', 'queen' => 'Q', 'king' => 'K', 'ace' => 'A',
        'الفالي' => 'J', 'فالي' => 'J', 'الدامة' => 'Q', 'دامة' => 'Q', 'الروا' => 'K', 'روا' => 'K', 'اللاص' => 'A', 'لاص' => 'A',
    ];
    private const array SUIT_WORDS = [
        'piques' => 'S', 'pique' => 'S', 'cœurs' => 'H', 'cœur' => 'H', 'coeurs' => 'H', 'coeur' => 'H',
        'carreaux' => 'D', 'carreau' => 'D', 'trèfles' => 'C', 'trèfle' => 'C', 'trefles' => 'C', 'trefle' => 'C',
        'spades' => 'S', 'spade' => 'S', 'hearts' => 'H', 'heart' => 'H', 'diamonds' => 'D', 'diamond' => 'D', 'clubs' => 'C', 'club' => 'C',
        'البيك' => 'S', 'بيك' => 'S', 'الكور' => 'H', 'كور' => 'H', 'الكارو' => 'D', 'كارو' => 'D', 'السباتة' => 'C', 'سباتة' => 'C',
    ];
    /** the letters printed in the corner of a card, French and English, before a suit symbol */
    private const array INDEX_LETTERS = ['t' => 'T', 'j' => 'J', 'q' => 'Q', 'k' => 'K', 'a' => 'A', 'v' => 'J', 'd' => 'Q', 'r' => 'K'];
    private const array SYMBOLS = ['♠' => 'S', '♥' => 'H', '♦' => 'D', '♣' => 'C', '♤' => 'S', '♡' => 'H', '♢' => 'D', '♧' => 'C'];

    private const string CODE = '/(?<![A-Za-z0-9])([789TJQKA])([SHDC])(?![A-Za-z0-9])/';

    /** The card as the screen names it in that language: "dame de cœur", "queen of hearts", "الدامة كور". */
    public static function name(string $card, string $lang): string
    {
        $rank = self::RANKS[$lang][$card[0] ?? ''] ?? null;
        $suit = self::SUITS[$lang][$card[1] ?? ''] ?? null;
        if (null === $rank || null === $suit || 2 !== \strlen($card)) {
            throw new \InvalidArgumentException(\sprintf('No name for "%s" in "%s".', $card, $lang));
        }

        return match ($lang) {
            'fr' => $rank.' de '.$suit,
            'en' => $rank.' of '.$suit,
            default => $rank.' '.$suit,
        };
    }

    /** The card in the middle of a sentence, with the article the language wants: "la dame de cœur", "l’as de pique", "the queen of hearts". */
    public static function inSentence(string $card, string $lang): string
    {
        $name = self::name($card, $lang);

        return match ($lang) {
            'fr' => match ($card[0]) {
                'A' => 'l’',
                'Q' => 'la ',
                default => 'le ',
            }.$name,
            'en' => 'the '.$name,
            default => $name,
        };
    }

    /** The suit as the screen names it in that language. */
    public static function suit(string $suit, string $lang): string
    {
        return self::SUITS[$lang][$suit] ?? throw new \InvalidArgumentException(\sprintf('No name for the suit "%s" in "%s".', $suit, $lang));
    }

    /**
     * The cards a text names, in any of the three languages, and the text without them. A rank or a
     * suit alone names no card. Codes ("QH") are read as written; names are read whatever their case.
     *
     * @return array{list<string>, string} the cards as codes, in order, and what is left of the text, lower case
     */
    public static function take(string $text): array
    {
        $cards = [];
        $keep = static function (string $rank, string $suit) use (&$cards): string {
            $cards[] = $rank.$suit;

            return ' ';
        };
        $text = (string) preg_replace_callback(self::CODE, static fn (array $m): string => $keep($m[1], $m[2]), $text);
        $text = mb_strtolower($text);

        $ranks = self::alternation(array_keys(self::RANK_WORDS));
        $suits = self::alternation(array_keys(self::SUIT_WORDS));
        $named = '/(?<![\p{L}\p{N}])('.$ranks.')\s+(?:de\s+|d[\'’]\s*|of\s+)?('.$suits.')(?![\p{L}\p{N}])/u';
        $text = (string) preg_replace_callback($named, static fn (array $m): string => $keep(self::RANK_WORDS[$m[1]], self::SUIT_WORDS[$m[2]]), $text);

        $letters = implode('', array_keys(self::INDEX_LETTERS));
        $symbols = implode('', array_keys(self::SYMBOLS));
        $drawn = '/(?<![\p{L}\p{N}])('.$ranks.'|['.$letters.'])\s?(?:de\s+|of\s+)?(['.$symbols.'])/u';
        $text = (string) preg_replace_callback(
            $drawn,
            static fn (array $m): string => $keep(self::RANK_WORDS[$m[1]] ?? self::INDEX_LETTERS[$m[1]], self::SYMBOLS[$m[2]]),
            $text,
        );

        return [$cards, $text];
    }

    /**
     * @param list<int|string> $words
     */
    private static function alternation(array $words): string
    {
        $words = array_map(strval(...), $words);
        // the longest first: "10" before "1", "dames" before "dame"
        usort($words, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), $words));
    }
}
