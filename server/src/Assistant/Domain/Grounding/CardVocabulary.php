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
    /** suits in Latin script: a card is then written with "de" or "of" between rank and suit */
    private const array LATIN_SUIT_WORDS = [
        'piques' => 'S', 'pique' => 'S', 'cœurs' => 'H', 'cœur' => 'H', 'coeurs' => 'H', 'coeur' => 'H',
        'carreaux' => 'D', 'carreau' => 'D', 'trèfles' => 'C', 'trèfle' => 'C', 'trefles' => 'C', 'trefle' => 'C',
        'spades' => 'S', 'spade' => 'S', 'hearts' => 'H', 'heart' => 'H', 'diamonds' => 'D', 'diamond' => 'D', 'clubs' => 'C', 'club' => 'C',
    ];
    /** suits in Arabic script: the rank is followed by the suit, with nothing between */
    private const array ARABIC_SUIT_WORDS = [
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
     * @return array{list<string>, string} the cards as codes, by the way they are written (codes, letters with a symbol, names, then ranks with a symbol), and what is left of the text, lower case
     */
    public static function take(string $text): array
    {
        $cards = [];
        $keep = static function (string $rank, string $suit) use (&$cards): string {
            $cards[] = $rank.$suit;

            return ' ';
        };
        $text = (string) preg_replace_callback(self::CODE, static fn (array $m): string => $keep($m[1], $m[2]), $text);
        // a letter printed in the corner of a card is a capital: "A♥" is the ace of hearts, "a ♥" is a heart
        $symbols = implode('', array_keys(self::SYMBOLS));
        $letters = implode('', array_map(strtoupper(...), array_keys(self::INDEX_LETTERS)));
        $text = (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}])(['.$letters.'])\s?(['.$symbols.'])/u',
            static fn (array $m): string => $keep(self::INDEX_LETTERS[strtolower($m[1])], self::SYMBOLS[$m[2]]),
            $text,
        );
        $text = mb_strtolower($text);
        // Arabic joins a word to what follows it: "و9 سباتة" (and the 9 of clubs), "للـ9" (for the 9). The link is cut before reading.
        $text = (string) preg_replace(['/ـ/u', '/(?<=[\x{0621}-\x{064A}])(?=\p{Nd})/u'], ['', ' '], $text);
        $ranks = self::alternation(array_keys(self::RANK_WORDS));
        // In French and English a card has "de" or "of" in it: without it, "as hearts were trumps" would be the ace of hearts.
        $latin = '/(?<![\p{L}\p{N}])('.$ranks.')\s+(?:de\s+|d[\'’]\s*|of\s+)('.self::alternation(array_keys(self::LATIN_SUIT_WORDS)).')(?![\p{L}\p{N}])/u';
        $text = (string) preg_replace_callback($latin, static fn (array $m): string => $keep(self::RANK_WORDS[$m[1]], self::LATIN_SUIT_WORDS[$m[2]]), $text);
        // In Arabic the suit follows the rank; one or two joined particles may come before the rank: "والدامة", "بالروا".
        $arabic = '/(?<![\p{L}\p{N}])(?:[وبفكل]{1,2}(?=\p{Arabic}))?('.$ranks.')\s+('.self::alternation(array_keys(self::ARABIC_SUIT_WORDS)).')(?![\p{L}\p{N}])/u';
        $text = (string) preg_replace_callback($arabic, static fn (array $m): string => $keep(self::RANK_WORDS[$m[1]], self::ARABIC_SUIT_WORDS[$m[2]]), $text);
        $drawn = '/(?<![\p{L}\p{N}])('.$ranks.')\s?(?:de\s+|of\s+)?(['.$symbols.'])/u';
        $text = (string) preg_replace_callback($drawn, static fn (array $m): string => $keep(self::RANK_WORDS[$m[1]], self::SYMBOLS[$m[2]]), $text);

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
