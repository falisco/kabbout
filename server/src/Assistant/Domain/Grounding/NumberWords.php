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
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    private const array WORDS = [
        'premier' => 1, 'première' => 1, 'premiere' => 1, 'deuxième' => 2, 'deuxieme' => 2, 'second' => 2, 'seconde' => 2, 'troisième' => 3, 'troisieme' => 3,
        'quatrième' => 4, 'quatrieme' => 4, 'cinquième' => 5, 'cinquieme' => 5, 'sixième' => 6, 'sixieme' => 6, 'septième' => 7, 'septieme' => 7, 'huitième' => 8, 'huitieme' => 8,
        'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10,
        'first' => 1, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8,
        'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'الأول' => 1, 'الاول' => 1, 'الأولى' => 1, 'الاولى' => 1, 'الثاني' => 2, 'الثانية' => 2, 'الثالث' => 3, 'الثالثة' => 3, 'الرابع' => 4, 'الرابعة' => 4,
        'الخامس' => 5, 'الخامسة' => 5, 'السادس' => 6, 'السادسة' => 6, 'السابع' => 7, 'السابعة' => 7, 'الثامن' => 8, 'الثامنة' => 8,
        'زوز' => 2, 'ثلاثة' => 3, 'أربعة' => 4, 'اربعة' => 4, 'خمسة' => 5, 'ستة' => 6, 'سبعة' => 7, 'ثمانية' => 8, 'تسعة' => 9, 'عشرة' => 10,
    ];

    /** The text with every number in the digits 0-9: the digits of Arabic script and the number words alike. Expects lower case. */
    public static function toDigits(string $text): string
    {
        $text = strtr($text, self::DIGITS);
        $words = array_keys(self::WORDS);
        usort($words, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $pattern = '/(?<![\p{L}\p{N}])('.implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), $words)).')(?![\p{L}\p{N}])/u';

        return (string) preg_replace_callback($pattern, static fn (array $m): string => (string) self::WORDS[$m[1]], $text);
    }
}
