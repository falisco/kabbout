<?php

declare(strict_types=1);

namespace Kabbout\Tests\Support;

/** Decoded JSON written one way only, so that two values that say the same thing compare as equal. */
final class Canonical
{
    /** The same value with the fields of every object in alphabetical order; lists keep their order. */
    public static function sorted(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        $sorted = array_map(self::sorted(...), $value);
        if (!array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }
}
