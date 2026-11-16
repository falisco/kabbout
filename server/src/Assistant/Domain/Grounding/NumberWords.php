<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/**
 * Numbers as a sentence may write them without digits, in the three languages: the digits of Arabic
 * script, the ordinals of the eight tricks ("troisième", "third", "الثالث") and the small cardinals.
 * "un", "une" and "one" are left alone: they are articles and pronouns far more often than numbers.
 */
final class NumberWords
{
    private const array DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** ordinals of French and English: written back as "3th", so that "the third trick" still points at a trick and "3 tricks" does not */
    private const array ORDINALS = [
        'premier' => 1, 'première' => 1, 'premiere' => 1, 'deuxième' => 2, 'deuxieme' => 2, 'second' => 2, 'seconde' => 2, 'troisième' => 3, 'troisieme' => 3,
        'quatrième' => 4, 'quatrieme' => 4, 'cinquième' => 5, 'cinquieme' => 5, 'sixième' => 6, 'sixieme' => 6, 'septième' => 7, 'septieme' => 7, 'huitième' => 8, 'huitieme' => 8,
        'first' => 1, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8,
    ];
    private const string ORDINAL_MARK = 'th';
    private const array WORDS = [
        'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10,
        'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'الأول' => 1, 'الاول' => 1, 'الأولى' => 1, 'الاولى' => 1, 'الثاني' => 2, 'الثانية' => 2, 'الثالث' => 3, 'الثالثة' => 3, 'الرابع' => 4, 'الرابعة' => 4,
        'الخامس' => 5, 'الخامسة' => 5, 'السادس' => 6, 'السادسة' => 6, 'السابع' => 7, 'السابعة' => 7, 'الثامن' => 8, 'الثامنة' => 8,
        'زوز' => 2, 'ثلاثة' => 3, 'أربعة' => 4, 'اربعة' => 4, 'خمسة' => 5, 'ستة' => 6, 'سبعة' => 7, 'ثمانية' => 8, 'تسعة' => 9, 'عشرة' => 10,
    ];

    /**
     * The text with every number in the digits 0-9: the digits of other scripts and the number words alike.
     * An ordinal of French or English keeps a mark after its digits ("third" becomes "3th"). Expects lower case.
     */
    public static function toDigits(string $text): string
    {
        // what cannot be seen is not left between two digits
        $text = strtr((string) preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text), self::DIGITS);

        return self::replace(self::replace($text, self::ORDINALS, self::ORDINAL_MARK), self::WORDS, '');
    }

    /** @param array<string, int> $words */
    private static function replace(string $text, array $words, string $mark): string
    {
        $known = array_keys($words);
        usort($known, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $pattern = '/(?<![\p{L}\p{N}])('.implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), $known)).')(?![\p{L}\p{N}])/u';

        return (string) preg_replace_callback($pattern, static fn (array $m): string => $words[$m[1]].$mark, $text);
    }
}
